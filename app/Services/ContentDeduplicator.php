<?php

namespace App\Services;

use App\Models\Content;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Quita frutinovelas repetidas de la colección "contents".
 *
 * 1. Mismo título (sin importar mayúsculas, tildes ni emojis):
 *    - del mismo canal: se fusionan; los capítulos que falten pasan al que se conserva.
 *    - de otro canal: es una resubida; se borra.
 * 2. Contenido incluido en otro: si todos sus videos ya están en otra frutinovela
 *    (p. ej. un video suelto que también es capítulo de una novela), se borra.
 *
 * Se conserva el más completo: más capítulos, publicado, con póster y, si empatan, el más antiguo.
 * Los identificadores fusionados quedan en source_ref.merged para que el importador no los vuelva a crear.
 */
class ContentDeduplicator
{
    public function __construct(private ContentSanitizer $sanitizer) {}

    /**
     * @return list<array{action: string, title: string, into: string, reason: string}>
     */
    public function run(bool $dryRun = false): array
    {
        /** @var Collection<string, Content> $alive */
        $alive = Content::where('source', Content::SOURCE_FRUTINOVELA)->get()->keyBy(fn (Content $c) => (string) $c->getKey());
        $report = [];
        $touched = [];

        // 1. Mismo título.
        $groups = $alive->groupBy(fn (Content $c) => $this->titleKey($c->title))->filter(fn ($group, $key) => $key !== '' && $group->count() > 1);

        foreach ($groups as $group) {
            [$keeper, $duplicates] = $this->pickKeeper($group);

            foreach ($duplicates as $duplicate) {
                if ($this->sameChannel($keeper, $duplicate)) {
                    $added = $this->merge($keeper, $duplicate);
                    $touched[(string) $keeper->getKey()] = $keeper;
                    $report[] = $this->entry('fusionado', $duplicate, $keeper, $added ? "{$added} capítulos nuevos" : 'sin capítulos nuevos');
                } else {
                    $report[] = $this->entry('borrado', $duplicate, $keeper, 'mismo título, resubido por otro canal');
                }

                $alive->forget((string) $duplicate->getKey());
                $dryRun || $duplicate->delete();
            }
        }

        // 2. Contenido cuyos videos ya están todos en otro (se revisa del más grande al más chico).
        $bySize = $alive->sort(fn (Content $a, Content $b) => [count($this->videoIds($b)), $this->score($b)] <=> [count($this->videoIds($a)), $this->score($a)])->values();

        foreach ($bySize as $i => $small) {
            $ids = $this->videoIds($small);

            if (! $ids) {
                continue;
            }

            foreach ($bySize->take($i) as $big) {
                if ($alive->has((string) $big->getKey()) && ! array_diff($ids, $this->videoIds($big))) {
                    $this->rememberMerged($big, $small);
                    $touched[(string) $big->getKey()] = $big;
                    $report[] = $this->entry('borrado', $small, $big, 'sus videos ya están en otra frutinovela');
                    $alive->forget((string) $small->getKey());
                    $touched = array_diff_key($touched, [(string) $small->getKey() => true]);
                    $dryRun || $small->delete();

                    break;
                }
            }
        }

        if (! $dryRun) {
            foreach ($touched as $content) {
                $content->save();
            }
        }

        return $report;
    }

    /**
     * "💔 El Amor de MANGO (Temporada 2)" y "el amor de mango" => "el-amor-de-mango"
     */
    public function titleKey(?string $title): string
    {
        $key = Str::slug(Str::ascii((string) $title));

        return preg_replace('/-(temporada|season|parte|part|completa|completo)(-\d+)?$/', '', $key);
    }

    /**
     * IDs de YouTube de un contenido: el del video suelto y los de todos sus capítulos.
     *
     * @return list<string>
     */
    public function videoIds(Content $content): array
    {
        $ids = array_map(fn ($ep) => $ep['video_id'] ?? $this->sanitizer->youTubeId($ep['embed_url'] ?? null), (array) ($content->episodes ?? []));
        $ids[] = $this->sanitizer->youTubeId($content->embed_url);

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * @return array{0: Content, 1: Collection<int, Content>}
     */
    private function pickKeeper(Collection $group): array
    {
        $sorted = $group->sort(fn (Content $a, Content $b) => $this->score($b) <=> $this->score($a))->values();

        return [$sorted->first(), $sorted->slice(1)];
    }

    // Más capítulos, publicado, con póster y, si empatan, el más antiguo.
    private function score(Content $content): array
    {
        return [
            count((array) ($content->episodes ?? [])),
            (int) (bool) $content->published,
            (int) filled($content->poster_url),
            -($content->created_at?->getTimestamp() ?? PHP_INT_MAX),
        ];
    }

    private function sameChannel(Content $a, Content $b): bool
    {
        $channel = fn (Content $c) => mb_strtolower($c->source_ref['channel_url'] ?? $c->source_ref['channel'] ?? '');

        // Sin canal en alguno de los dos no hay forma de saber: se fusiona (no se pierde nada).
        return $channel($a) === '' || $channel($b) === '' || $channel($a) === $channel($b);
    }

    // Pasa al que se conserva los capítulos que no tenga. Devuelve cuántos agregó.
    private function merge(Content $keeper, Content $duplicate): int
    {
        $episodes = (array) ($keeper->episodes ?? []);
        $known = array_flip(array_map(fn ($ep) => $this->sanitizer->episodeKey($ep), $episodes));
        $incoming = (array) ($duplicate->episodes ?? []);

        // Un video suelto sin capítulos cuenta como un capítulo.
        if (! $incoming && $id = $this->sanitizer->youTubeId($duplicate->embed_url)) {
            $incoming = [['season' => 1, 'number' => 1, 'title' => $duplicate->title, 'duration' => $duplicate->duration, 'video_id' => $id, 'thumbnail_url' => $duplicate->poster_url]];
        }

        // Temporadas que el que se conserva ya tiene: lo nuevo va al final de su última temporada.
        $seasons = array_unique(array_map(fn ($ep) => $ep['season'] ?? 1, $episodes));
        $lastSeason = $seasons ? max($seasons) : 1;
        $lastNumber = max([0, ...array_map(fn ($ep) => $ep['number'] ?? 0, $episodes)]);
        $added = 0;

        foreach ($incoming as $episode) {
            if (isset($known[$this->sanitizer->episodeKey($episode)])) {
                continue;
            }

            if (in_array($episode['season'] ?? 1, $seasons, true)) {
                $episode['season'] = $lastSeason;
                $episode['number'] = $lastNumber + 1000 + $added;   // el sanitizador los renumera de corrido
            }

            $episodes[] = $episode;
            $known[$this->sanitizer->episodeKey($episode)] = true;
            $added++;
        }

        if ($added) {
            $keeper->episodes = $episodes;
            // Un video suelto que recibe capítulos pasa a ser novela.
            if ($keeper->type === 'corto') {
                $keeper->type = 'novela';
                $keeper->embed_url = null;
                $keeper->duration = null;
            }
        }

        $this->rememberMerged($keeper, $duplicate);

        return $added;
    }

    private function rememberMerged(Content $keeper, Content $duplicate): void
    {
        $ref = (array) ($keeper->source_ref ?? []);
        $ref['merged'] = array_values(array_unique(array_filter([
            ...($ref['merged'] ?? []),
            $duplicate->source_ref['identifier'] ?? null,
            ...($duplicate->source_ref['merged'] ?? []),
        ])));
        $keeper->source_ref = $ref;
    }

    private function entry(string $action, Content $content, Content $into, string $reason): array
    {
        return ['action' => $action, 'title' => (string) $content->title, 'into' => (string) $into->title, 'reason' => $reason];
    }
}
