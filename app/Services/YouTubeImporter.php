<?php

namespace App\Services;

use App\Models\Content;
use Illuminate\Support\Str;

/**
 * Guarda frutinovelas de YouTube en la colección "contents" a partir de los metadatos
 * que exporta scripts/youtube_catalog.py (yt-dlp).
 *
 * Solo se guardan metadatos: los videos se reproducen con el reproductor oficial de YouTube
 * y el crédito queda en source_ref.channel. Los videos largos se dividen en capítulos
 * guardando solo el segundo de inicio y de fin de cada uno (nada se descarga ni se corta).
 */
class YouTubeImporter
{
    // Un video de más de 12 minutos se divide en capítulos de unos 8.
    private const SPLIT_FROM = 12 * 60;
    private const TARGET_PART = 8 * 60;
    // Un final de menos de 3 minutos se une al capítulo anterior.
    private const MIN_TAIL = 3 * 60;

    private ?string $skipReason = null;

    public function __construct(
        private AdultContentFilter $adultFilter,
        private GenreCategorizer $categorizer,
    ) {}

    public function skipReason(): ?string
    {
        return $this->skipReason;
    }

    /**
     * Un video suelto. Si es largo (recopilaciones de "1 hora") se convierte en novela con capítulos.
     */
    public function importVideo(array $video): ?Content
    {
        $this->skipReason = null;
        $title = $this->cleanTitle($video['title'] ?? '');
        $synopsis = $this->cleanDescription($video['description'] ?? null, $title);
        $tags = array_values((array) ($video['tags'] ?? []));

        if ($this->rejected($title, $tags, $synopsis)) {
            return null;
        }

        $segments = $this->segments($video);
        $genres = $this->genres($tags, $title, $synopsis);
        $isSeries = count($segments) > 1;

        return $this->save($video['id'], [
            'title' => $title,
            'type' => $isSeries ? 'novela' : 'corto',
            'synopsis' => $synopsis,
            'tags' => array_slice($tags, 0, 15),
            'genres' => $genres,
            'maturity' => $this->categorizer->estimateMaturity($genres),
            'year' => $this->yearFrom($video['upload_date'] ?? null),
            'duration' => $isSeries ? null : $this->minutes($video['duration'] ?? null),
            'poster_url' => $this->thumbnail($video),
            'embed_url' => $isSeries ? null : $this->embedUrl($video),
            'episodes' => $isSeries ? $this->episodesFrom([$video], 1, $title) : [],
            'source_ref' => $this->sourceRef($video['id'], $video),
        ]);
    }

    /**
     * Una serie real (lista de reproducción del canal): cada video es un capítulo,
     * y los capítulos largos también se dividen.
     *
     * @param  array<int, array>  $videos
     */
    public function importPlaylist(array $playlist, array $videos): ?Content
    {
        $this->skipReason = null;

        if (! $videos) {
            $this->skipReason = 'la lista no tiene videos que se puedan insertar';

            return null;
        }

        // La temporada se guarda en cada capítulo: "Amor de Mango Temporada 1" => "Amor de Mango".
        $title = preg_replace('/\s*\(?\s*(temporada|season)\s*\d+\s*\)?$/iu', '', $this->cleanTitle($playlist['title'] ?? $videos[0]['title'] ?? ''));

        // Listas llamadas solo "Frutinovelas": se distinguen por el canal.
        if (preg_match('/^fruti\s?novelas?$/iu', $title) && filled($playlist['channel'] ?? null)) {
            $title = "Frutinovelas de {$playlist['channel']}";
        }

        $synopsis = $this->cleanDescription($playlist['description'] ?? null, $title)
            ?? $this->cleanDescription($videos[0]['description'] ?? null, $this->cleanTitle($videos[0]['title'] ?? ''));
        $tags = collect($videos)->flatMap(fn ($v) => (array) ($v['tags'] ?? []))->unique()->values()->all();

        if ($this->rejected($title, $tags, $synopsis)) {
            return null;
        }

        // Si un capítulo es +18, se quita ese capítulo (no toda la novela).
        $videos = $this->inStoryOrder(array_values(array_filter(
            $videos,
            fn ($v) => ! $this->adultFilter->isAdult($v['title'] ?? '', $v['tags'] ?? [], $v['description'] ?? null),
        )));

        $season = preg_match('/temporada\s*(\d+)|season\s*(\d+)/iu', $playlist['title'] ?? '', $m) ? (int) ($m[1] ?: $m[2]) : 1;
        $genres = $this->genres($tags, $title, $synopsis);

        return $this->save($playlist['id'], [
            'title' => $title,
            'type' => 'novela',
            'synopsis' => $synopsis,
            'tags' => array_slice($tags, 0, 15),
            'genres' => $genres,
            'maturity' => $this->categorizer->estimateMaturity($genres),
            'year' => $this->yearFrom($videos[0]['upload_date'] ?? null),
            'duration' => null,
            'poster_url' => $this->thumbnail($videos[0]),
            'embed_url' => null,
            'episodes' => $this->episodesFrom($videos, $season, $title),
            'source_ref' => $this->sourceRef($playlist['id'], $playlist),
        ]);
    }

    /**
     * Partes de un video: [{title, start, end}] en segundos. Un video corto es una sola parte sin cortes.
     *
     * @return array<int, array{title: ?string, start: ?int, end: ?int}>
     */
    public function segments(array $video): array
    {
        $length = (int) ($video['duration'] ?? 0);

        if ($length <= self::SPLIT_FROM) {
            return [['title' => null, 'start' => null, 'end' => null]];
        }

        $chapters = array_values(array_filter(
            (array) ($video['chapters'] ?? []),
            fn ($c) => isset($c['start'], $c['end']) && $c['end'] > $c['start'],
        ));

        if (! $chapters) {
            return $this->evenParts(0, $length);
        }

        // Si una escena marcada es enorme (p. ej. la última dura 54 minutos), también se divide.
        $segments = [];

        foreach ($this->groupChapters($chapters, $length) as $part) {
            if ($part['end'] - $part['start'] <= self::SPLIT_FROM) {
                $segments[] = $part;

                continue;
            }

            // La primera parte conserva el nombre de la escena.
            foreach ($this->evenParts($part['start'], $part['end']) as $i => $sub) {
                $segments[] = ['title' => $i === 0 ? $part['title'] : null] + $sub;
            }
        }

        return $segments;
    }

    // Junta escenas consecutivas hasta formar capítulos de ~8 minutos; el título es el de la primera escena.
    private function groupChapters(array $chapters, int $length): array
    {
        $parts = [];
        $current = null;

        foreach ($chapters as $chapter) {
            $current ??= ['title' => $chapter['title'] ?? null, 'start' => (int) $chapter['start'], 'end' => (int) $chapter['end']];
            $current['end'] = (int) $chapter['end'];

            if ($current['end'] - $current['start'] >= self::TARGET_PART) {
                $parts[] = $current;
                $current = null;
            }
        }

        if ($current) {
            // Un final muy corto se une al capítulo anterior.
            if ($parts && $current['end'] - $current['start'] < self::MIN_TAIL) {
                $parts[array_key_last($parts)]['end'] = $current['end'];
            } else {
                $parts[] = $current;
            }
        }

        $parts[array_key_last($parts)]['end'] = max($parts[array_key_last($parts)]['end'], $length);

        return $parts;
    }

    // Partes iguales de ~8 minutos entre dos segundos del video (sin escenas marcadas, o una escena muy larga).
    private function evenParts(int $from, int $to): array
    {
        $length = $to - $from;
        $count = max(2, (int) round($length / self::TARGET_PART));
        $size = (int) ceil($length / $count);

        return array_map(fn ($i) => [
            'title' => null,
            'start' => $from + $i * $size,
            'end' => min($to, $from + ($i + 1) * $size),
        ], range(0, $count - 1));
    }

    /**
     * @param  array<int, array>  $videos  en orden
     */
    private function episodesFrom(array $videos, int $season, string $seriesTitle): array
    {
        $episodes = [];

        foreach ($videos as $video) {
            $segments = $this->segments($video);
            $base = $this->cleanEpisodeTitle($video['title'] ?? '', $seriesTitle);

            foreach ($segments as $i => $segment) {
                $number = count($episodes) + 1;
                $title = match (true) {
                    // Video partido: el nombre de la escena si lo hay; si no, "Parte N".
                    count($segments) > 1 && filled($segment['title']) => $this->cleanEpisodeTitle($segment['title'], $seriesTitle),
                    count($segments) > 1 && count($videos) > 1 && $base !== '' => "{$base} (parte ".($i + 1).')',
                    count($segments) > 1 => 'Parte '.($i + 1),
                    default => $base,
                };
                $seconds = $segment['start'] !== null ? $segment['end'] - $segment['start'] : ($video['duration'] ?? null);

                $episodes[] = array_filter([
                    'season' => $season,
                    'number' => $number,
                    'title' => $title !== '' ? $title : "Capítulo {$number}",
                    'duration' => $this->minutes($seconds),
                    'video_id' => $video['id'],
                    'start' => $segment['start'],
                    'end' => $segment['end'],
                    'embed_url' => $this->embedUrl($video),
                    'thumbnail_url' => $this->thumbnail($video),
                ], fn ($value) => $value !== null);
            }
        }

        return $episodes;
    }

    private function rejected(string $title, array $tags, ?string $synopsis): bool
    {
        if ($title === '') {
            $this->skipReason = 'sin título';
        } elseif ($this->adultFilter->isAdult($title, $tags, $synopsis)) {
            $this->skipReason = 'material +18';
        }

        return $this->skipReason !== null;
    }

    private function save(string $identifier, array $attributes): Content
    {
        $content = Content::where('source', Content::SOURCE_FRUTINOVELA)
            ->where('source_ref.identifier', $identifier)
            ->first() ?? new Content;

        $content->fill($attributes + [
            'source' => Content::SOURCE_FRUTINOVELA,
            'license' => 'youtube-embed',
            'language' => 'es',
            'video_url' => null,
            'published' => true,
        ])->save();

        return $content;
    }

    private function sourceRef(string $identifier, array $data): array
    {
        return array_filter([
            'identifier' => $identifier,
            'url' => $data['url'] ?? null,
            'channel' => $data['channel'] ?? null,
            'channel_url' => $data['channel_url'] ?? null,
        ]);
    }

    private function embedUrl(array $video): string
    {
        return $video['embed_url'] ?? "https://www.youtube-nocookie.com/embed/{$video['id']}";
    }

    private function thumbnail(array $video): string
    {
        return $video['thumbnail'] ?? "https://i.ytimg.com/vi/{$video['id']}/hqdefault.jpg";
    }

    // Las frutinovelas son animadas: Animación siempre va, después de los géneros detectados.
    private function genres(array $tags, string $title, ?string $synopsis): array
    {
        $genres = $this->categorizer->categorize($tags, $title, $synopsis) ?: ['Drama'];

        return array_values(array_unique([...$genres, 'Animación']));
    }

    /**
     * "💔 EL AMOR QUE NUNCA DEBIÓ EXISTIR 😭 | FRUTINOVELA COMPLETA 🍎" => "El amor que nunca debió existir"
     */
    public function cleanTitle(string $title): string
    {
        // Quita emojis y hashtags. Nada de trim() con "·" o "…": trim() corta por bytes y rompe caracteres como "¡".
        // El orden importa: primero se recortan los bordes y luego se quita "frutinovelas" al inicio o al final.
        $edges = '/^[\s\-|:·.…]+|[\s\-|:·.…]+$/u';
        $clean = fn (string $text) => preg_replace(
            ['/[\p{So}\p{Sk}\x{FE0F}\x{200D}]/u', '/#\S+/u', '/\s{2,}/u', $edges, '/^fruti\s?novelas?:?\s+|\s+fruti\s?novelas?(\s+completas?)?$/iu', $edges],
            ['', '', ' ', '', '', ''],
            $text,
        );

        // Fragmentos que solo son la etiqueta del canal o de la lista
        // ("FRUTINOVELA COMPLETA", "Frutinovela Completa (Temporada 1)", "Episodios en orden").
        $isLabel = fn (string $part) => preg_replace(
            '/\b(fruti\s?novelas?|completas?|historias?|de|las|frutas?|en|español|latino|temporada|episodios?|cap[ií]tulos?|orden)\b|\d+|[()\[\]\s.,:\-]/iu',
            '',
            $part,
        ) === '';

        // La primera parte con texto propio: el nombre de la historia suele ir antes del "|".
        $parts = collect(preg_split('/\s*[|｜]\s*/u', $title))->map($clean)->filter();
        $title = $parts->reject($isLabel)->first() ?? $parts->first() ?? '';

        // Títulos casi todo en MAYÚSCULAS: pasarlos a formato oración.
        $letters = preg_replace('/\P{L}/u', '', $title);
        $upper = preg_replace('/\P{Lu}/u', '', $title);

        if ($letters !== '' && mb_strlen($upper) / mb_strlen($letters) > 0.5) {
            $title = mb_strtolower($title);
        }

        // Mayúscula en la primera letra, aunque empiece con "¡" o "¿" (pero "1 hora", no "1 Hora").
        $title = preg_replace_callback('/^([¡¿"«\']*)(\p{Ll})/u', fn ($m) => $m[1].mb_strtoupper($m[2]), $title);

        return Str::limit($title, 200, '');
    }

    /**
     * "FrutiNovelas EP. 1: ¿De cero a 50.000 millones? | El Número en mi Frente" => "¿De cero a 50.000 millones?"
     */
    public function cleanEpisodeTitle(string $title, string $seriesTitle = ''): string
    {
        // Cada parte del "|" por separado: en "Cereza capítulo 18 | Una historia que llega a su final"
        // la primera queda vacía al quitar la serie y el número, y el nombre real es la segunda.
        foreach (preg_split('/\s*[|｜]\s*/u', $title) as $part) {
            $part = $this->cleanTitle($part);

            if ($seriesTitle !== '') {
                $part = str_ireplace($seriesTitle, '', $part);
            }

            $part = preg_replace(
                [
                    '/^[\s,\-:·.…]+/u',
                    '/^(ep|episodio|cap[ií]tulo|cap|parte)\.?\s*\d*\s*[:.,\-–]?\s*/iu',   // "capitulo 2, ..." / "EP. 1: ..."
                    '/^\d+\s+(?=\p{L})/u',                                             // "19 Después de esto…"
                    '/^[\s,\-|:·.…]+|[\s,\-|:·.…]+$/u',
                ],
                '',
                $part,
            );

            if ($part !== '' && ! preg_match('/^(final|completo|completa)$/iu', $part)) {
                return preg_replace_callback('/^([¡¿"«\']*)(\p{Ll})/u', fn ($m) => $m[1].mb_strtoupper($m[2]), $part);
            }
        }

        return preg_match('/\bfinal\b/iu', $title) ? 'Capítulo final' : '';
    }

    // Número de capítulo escrito en el título ("capítulo 18", "EP. 3", "capítulo | 19").
    private function episodeNumber(string $title): ?int
    {
        return preg_match('/\b(?:ep|episodio|cap[ií]tulo|cap|parte)\.?[\s|]*(\d+)/iu', $title, $m) ? (int) $m[1] : null;
    }

    // Algunos canales ordenan la lista del más nuevo al más viejo: si los números van bajando, se invierte.
    private function inStoryOrder(array $videos): array
    {
        $numbers = array_values(array_filter(array_map(fn ($v) => $this->episodeNumber($v['title'] ?? ''), $videos)));

        return count($numbers) >= 2 && $numbers[0] > end($numbers) ? array_reverse($videos) : $videos;
    }

    // Quita hashtags, enlaces, llamados a suscribirse y la línea que solo repite el título.
    private function cleanDescription(?string $text, string $title = ''): ?string
    {
        if (blank($text)) {
            return null;
        }

        $lines = collect(preg_split('/\R/u', $text))
            ->map(fn ($line) => trim(preg_replace(['/https?:\/\/\S+/u', '/#\S+/u', '/[\p{So}\x{FE0F}\x{200D}]/u'], '', $line)))
            ->reject(fn ($line) => $line === ''
                || preg_match('/suscr[ií]b|like|comenta|s[ií]gueme|redes sociales|instagram|tiktok/iu', $line)
                || ($title !== '' && mb_strtolower($this->cleanTitle($line)) === mb_strtolower($title)));

        $synopsis = Str::limit($lines->implode(' '), 600);

        return $synopsis !== '' ? $synopsis : null;
    }

    private function minutes(?int $seconds): ?int
    {
        return $seconds ? max(1, (int) round($seconds / 60)) : null;
    }

    private function yearFrom(?string $uploadDate): ?int
    {
        return $uploadDate && preg_match('/^(\d{4})/', $uploadDate, $m) ? (int) $m[1] : null;
    }
}
