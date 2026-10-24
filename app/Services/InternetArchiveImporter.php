<?php

namespace App\Services;

use App\Models\Content;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Trae películas de Internet Archive (archive.org) y las guarda en la colección "contents".
 *
 * Usa dos APIs públicas, sin clave:
 *  - advancedsearch.php: busca identificadores.
 *  - metadata/{id}: detalle del item y lista de archivos (para elegir el MP4).
 *
 * Solo se publica automáticamente lo que trae una licencia libre reconocible.
 * El resto se guarda con published = false para que un admin lo revise.
 */
class InternetArchiveImporter
{
    private const BASE = 'https://archive.org';

    // Formatos de video que los navegadores reproducen directamente, en orden de preferencia.
    private const VIDEO_FORMATS = ['h.264', 'h.264 HD', 'MPEG4', '512Kb MPEG4'];

    // Se excluyen desde la búsqueda; lo que pase igual lo revisa AdultContentFilter al importar.
    private const ADULT_SUBJECTS = 'sex OR sexploitation OR nudity OR nudist OR erotica OR erotic OR "nudie cutie" OR stripping OR adult';

    public function __construct(
        private AdultContentFilter $adultFilter,
        private GenreCategorizer $categorizer,
    ) {}

    /**
     * @return array<int, string> identificadores encontrados
     */
    public function search(string $query, int $rows = 20, int $page = 1): array
    {
        $response = Http::timeout(30)->get(self::BASE.'/advancedsearch.php', [
            'q' => "({$query}) AND mediatype:(movies) AND NOT subject:(".self::ADULT_SUBJECTS.')',
            'fl' => ['identifier'],
            'sort' => ['downloads desc'],
            'rows' => $rows,
            'page' => $page,
            'output' => 'json',
        ])->throw();

        return Arr::pluck($response->json('response.docs', []), 'identifier');
    }

    /**
     * Importa (o actualiza) un item por su identificador.
     * Devuelve null si no tiene video reproducible o si es material +18 (ver skipReason()).
     */
    public function import(string $identifier): ?Content
    {
        $this->skipReason = null;
        $data = Http::timeout(30)->get(self::BASE."/metadata/{$identifier}")->throw()->json();
        $meta = $data['metadata'] ?? null;

        if (! $meta) {
            $this->skipReason = 'no existe';

            return null;
        }

        $title = trim($this->first($meta['title'] ?? $identifier));
        $tags = $this->tagsFrom($meta['subject'] ?? []);
        $synopsis = $this->cleanText($this->first($meta['description'] ?? null));

        if ($this->adultFilter->isAdult($title, $tags, $synopsis)) {
            $this->skipReason = 'material +18';

            return null;
        }

        $video = $this->pickVideo($data['files'] ?? []);

        if (! $video) {
            $this->skipReason = 'sin video reproducible';

            return null;
        }

        $licenseUrl = $this->first($meta['licenseurl'] ?? null);
        $license = $this->licenseFrom($licenseUrl, (array) ($meta['collection'] ?? []));
        $genres = $this->categorizer->categorize($tags, $title, $synopsis);

        $attributes = [
            'title' => Str::limit($title, 250, ''),
            'type' => $this->typeFrom($video['minutes']),
            'source' => Content::SOURCE_INTERNET_ARCHIVE,
            'source_ref' => [
                'identifier' => $identifier,
                'url' => self::BASE."/details/{$identifier}",
            ],
            'license' => $license,
            'license_url' => $licenseUrl,
            'synopsis' => $synopsis,
            'genres' => $genres,
            'tags' => array_slice($tags, 0, 15),   // etiquetas originales (actores, épocas…), útiles para buscar
            'maturity' => $this->categorizer->estimateMaturity($genres),
            'year' => $this->yearFrom($meta),
            'duration' => $video['minutes'],
            'language' => $this->first($meta['language'] ?? null),
            'poster_url' => self::BASE."/services/img/{$identifier}",
            'video_url' => self::BASE."/download/{$identifier}/".rawurlencode($video['name']),
            'published' => $license !== 'desconocida',
        ];

        $content = Content::where('source', Content::SOURCE_INTERNET_ARCHIVE)
            ->where('source_ref.identifier', $identifier)
            ->first() ?? new Content;

        $content->fill($attributes)->save();

        return $content;
    }

    // Por qué el último import() devolvió null.
    private ?string $skipReason = null;

    public function skipReason(): ?string
    {
        return $this->skipReason;
    }

    // Etiquetas originales. "subject" puede venir como arreglo o como texto separado por ";" ("Drama; Horror; Thriller").
    private function tagsFrom(array|string $subjects): array
    {
        return collect((array) $subjects)
            ->flatMap(fn ($subject) => explode(';', $subject))
            ->map(fn ($subject) => trim($subject))
            ->reject(fn ($subject) => $subject === '' || in_array(strtolower($subject), ['pdmovies', 'public domain', 'full movie'], true))
            ->map(fn ($subject) => Str::ucfirst($subject))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array{name: string, minutes: int|null}|null
     */
    private function pickVideo(array $files): ?array
    {
        foreach (self::VIDEO_FORMATS as $format) {
            foreach ($files as $file) {
                if (($file['format'] ?? null) === $format && ($file['source'] ?? null) !== 'metadata') {
                    return ['name' => $file['name'], 'minutes' => $this->minutesFrom($file['length'] ?? null)];
                }
            }
        }

        return null;
    }

    // "length" llega como segundos ("5400.12") o como "hh:mm:ss".
    private function minutesFrom(?string $length): ?int
    {
        if (blank($length)) {
            return null;
        }

        if (str_contains($length, ':')) {
            $seconds = 0;
            foreach (explode(':', $length) as $part) {
                $seconds = $seconds * 60 + (float) $part;
            }
        } else {
            $seconds = (float) $length;
        }

        return $seconds > 0 ? max(1, (int) round($seconds / 60)) : null;
    }

    private function typeFrom(?int $minutes): string
    {
        return $minutes !== null && $minutes < 40 ? 'corto' : 'pelicula';
    }

    private function licenseFrom(?string $url, array $collections): string
    {
        $url = strtolower((string) $url);

        return match (true) {
            str_contains($url, 'publicdomain/zero') => 'cc0',
            str_contains($url, 'publicdomain') => 'dominio-publico',
            str_contains($url, '/by-sa/') => 'cc-by-sa',
            // Solo aceptamos BY sin NC/ND: se puede mostrar sin restricciones comerciales ni de edición.
            str_contains($url, '/by/') => 'cc-by',
            // La colección "feature_films" de archive.org es curada como dominio público.
            blank($url) && in_array('feature_films', $collections, true) => 'dominio-publico',
            default => 'desconocida',
        };
    }

    private function yearFrom(array $meta): ?int
    {
        $raw = $this->first($meta['year'] ?? $meta['date'] ?? null);

        return $raw && preg_match('/\d{4}/', $raw, $m) ? (int) $m[0] : null;
    }

    private function cleanText(?string $html): ?string
    {
        if (blank($html)) {
            return null;
        }

        $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html))));

        return Str::limit($text, 4900);
    }

    // Los campos de metadata de archive.org pueden venir como texto o como arreglo.
    private function first(mixed $value): ?string
    {
        return is_array($value) ? ($value[0] ?? null) : $value;
    }
}
