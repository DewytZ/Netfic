<?php

namespace App\Services;

use App\Models\Content;
use Illuminate\Support\Str;

/**
 * Limpia cualquier contenido antes de guardarlo en MongoDB, venga de donde venga
 * (importadores, panel de admin, API o seeders). Se aplica en Content::saving().
 *
 * - Texto: sin etiquetas HTML, sin caracteres de control ni invisibles, espacios normalizados.
 * - URLs: solo http/https bien formadas; las de YouTube quedan como youtube-nocookie.com/embed/ID.
 * - Capítulos: sin repetidos, ordenados y numerados de corrido dentro de cada temporada.
 */
class ContentSanitizer
{
    public const EMBED_BASE = 'https://www.youtube-nocookie.com/embed/';

    private const YOUTUBE_HOSTS = ['youtube.com', 'youtube-nocookie.com', 'youtu.be'];

    // Hosts que siempre aceptan https: se sube de http a https.
    private const HTTPS_HOSTS = ['youtube.com', 'youtube-nocookie.com', 'youtu.be', 'ytimg.com', 'i.ytimg.com', 'archive.org'];

    public function apply(Content $content): void
    {
        $text = [
            'title' => 250,
            'synopsis' => 5000,
            'language' => 10,
            'maturity' => 10,
        ];

        foreach ($text as $field => $max) {
            if ($content->getAttribute($field) !== null) {
                $content->setAttribute($field, $this->text($content->getAttribute($field), $max, $field === 'synopsis'));
            }
        }

        // Un título no termina ni empieza con separadores sueltos ("Entre Fresas y Celos –").
        if (is_string($content->title)) {
            $content->title = preg_replace('/^[\s\-–—|:·,]+|[\s\-–—|:·,]+$/u', '', $content->title);
        }

        foreach (['poster_url', 'backdrop_url', 'video_url', 'license_url'] as $field) {
            if ($content->getAttribute($field) !== null) {
                $content->setAttribute($field, $this->url($content->getAttribute($field)));
            }
        }

        if ($content->getAttribute('embed_url') !== null) {
            $content->setAttribute('embed_url', $this->embedUrl($content->getAttribute('embed_url')));
        }

        foreach (['genres' => 10, 'tags' => 30] as $field => $max) {
            if ($content->getAttribute($field) !== null) {
                $content->setAttribute($field, $this->list($content->getAttribute($field), $max));
            }
        }

        if (is_array($content->getAttribute('source_ref'))) {
            $content->setAttribute('source_ref', $this->sourceRef($content->getAttribute('source_ref')));
        }

        if ($content->getAttribute('episodes') !== null) {
            $content->setAttribute('episodes', $this->episodes($content->getAttribute('episodes')));
        }
    }

    /**
     * Texto plano: sin HTML, sin caracteres de control ni invisibles y con los espacios normalizados.
     */
    public function text(mixed $value, int $max = 255, bool $multiline = false): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $text = mb_scrub((string) $value, 'UTF-8');
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Solo etiquetas reales: "<3" o "a < b" se conservan (strip_tags() se los comería).
        $text = preg_replace(['/<(script|style)\b[^>]*>.*?<\/\1\s*>/isu', '/<!--.*?-->/su', '/<\/?[a-z][^>]*>/iu'], ' ', $text);

        // Caracteres de control (menos el salto de línea) y los invisibles que se usan para engañar:
        // espacios de ancho cero, marcas y controles de dirección del texto, BOM.
        $text = preg_replace(['/(?!\n)\p{Cc}/u', '/[\x{200B}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]/u'], ['', ''], $text);

        $text = $multiline
            ? preg_replace(['/[^\S\n]+/u', '/ *\n */u', '/\n{3,}/u'], [' ', "\n", "\n\n"], $text)
            : preg_replace('/\s+/u', ' ', $text);

        $text = Str::limit(trim($text), $max, '');

        return $text !== '' ? $text : null;
    }

    /**
     * URL http/https bien formada, o null.
     */
    public function url(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $url = trim($value);

        if (str_starts_with($url, '//')) {
            $url = 'https:'.$url;
        }

        if ($url === '' || preg_match('/[\s<>"\'`\\\\]/u', $url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');

        if (! in_array($scheme, ['http', 'https'], true) || empty($parts['host'])) {
            return null;
        }

        if ($scheme === 'http' && $this->hostIs($parts['host'], self::HTTPS_HOSTS)) {
            $url = 'https'.substr($url, 4);
        }

        return $url;
    }

    /**
     * ID de un video de YouTube a partir de cualquier enlace (watch?v=, youtu.be/, /embed/, /shorts/).
     */
    public function youTubeId(mixed $url): ?string
    {
        $url = $this->url($url);

        if (! $url) {
            return null;
        }

        $parts = parse_url($url);

        if (! $this->hostIs($parts['host'], self::YOUTUBE_HOSTS)) {
            return null;
        }

        $path = $parts['path'] ?? '';

        if ($this->hostIs($parts['host'], ['youtu.be'])) {
            $id = trim($path, '/');
        } elseif (preg_match('~^/(?:embed|shorts|live|v)/([^/]+)~', $path, $m)) {
            $id = $m[1];
        } else {
            parse_str($parts['query'] ?? '', $query);
            $id = $query['v'] ?? null;
        }

        return $this->videoId($id);
    }

    public function videoId(mixed $id): ?string
    {
        return is_string($id) && preg_match('/^[\w-]{1,32}$/', $id) ? $id : null;
    }

    /**
     * Cualquier enlace de YouTube => https://www.youtube-nocookie.com/embed/ID (sin parámetros de rastreo).
     * Lo que no sea de YouTube se descarta: el campo embed_url solo admite el reproductor de YouTube.
     */
    public function embedUrl(mixed $url): ?string
    {
        $id = $this->youTubeId($url);

        return $id ? self::EMBED_BASE.$id : null;
    }

    /**
     * Capítulos limpios: sin repetidos, en orden y numerados 1, 2, 3… dentro de cada temporada.
     */
    public function episodes(mixed $episodes): array
    {
        if (! is_array($episodes)) {
            return [];
        }

        $clean = [];
        $seen = [];

        foreach (array_values($episodes) as $i => $episode) {
            if (! is_array($episode)) {
                continue;
            }

            $videoId = $this->videoId($episode['video_id'] ?? null) ?? $this->youTubeId($episode['embed_url'] ?? null);
            $start = $this->seconds($episode['start'] ?? null);
            $end = $this->seconds($episode['end'] ?? null);

            if ($end !== null && $end <= ($start ?? 0)) {
                $end = null;
            }

            $item = array_filter([
                'season' => max(1, (int) ($episode['season'] ?? 1)),
                'number' => max(1, (int) ($episode['number'] ?? $i + 1)),
                'title' => $this->text($episode['title'] ?? null, 255),
                'synopsis' => $this->text($episode['synopsis'] ?? null, 2000, true),
                'duration' => $this->seconds($episode['duration'] ?? null) ?: null,
                'video_id' => $videoId,
                'start' => $start,
                'end' => $end,
                'video_url' => $this->url($episode['video_url'] ?? null),
                // El enlace se arma desde el ID: así embed_url y video_id nunca apuntan a videos distintos.
                'embed_url' => $videoId ? self::EMBED_BASE.$videoId : null,
                'thumbnail_url' => $this->url($episode['thumbnail_url'] ?? null),
            ], fn ($value) => $value !== null);

            $key = $this->episodeKey($item);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $clean[] = $item;
        }

        // usort es estable: los capítulos con el mismo número conservan el orden en que llegaron.
        usort($clean, fn ($a, $b) => [$a['season'], $a['number']] <=> [$b['season'], $b['number']]);

        $numbers = [];

        foreach ($clean as &$item) {
            $item['number'] = $numbers[$item['season']] = ($numbers[$item['season']] ?? 0) + 1;
            $item['title'] ??= "Capítulo {$item['number']}";
        }

        return $clean;
    }

    /**
     * Qué hace igual a dos capítulos: el mismo video (y el mismo tramo), o, sin video, la misma temporada y título.
     */
    public function episodeKey(array $episode): string
    {
        $video = $episode['video_id'] ?? $episode['video_url'] ?? null;

        return $video
            ? 'v:'.$video.'@'.($episode['start'] ?? 0).'-'.($episode['end'] ?? '')
            : 't:'.($episode['season'] ?? 1).':'.mb_strtolower($episode['title'] ?? '');
    }

    private function sourceRef(array $ref): array
    {
        return array_filter([
            'identifier' => $this->text($ref['identifier'] ?? null, 255),
            'url' => $this->url($ref['url'] ?? null),
            'channel' => $this->text($ref['channel'] ?? null, 255),
            'channel_url' => $this->url($ref['channel_url'] ?? null),
            // Identificadores de duplicados que se fusionaron en este contenido (ver ContentDeduplicator).
            'merged' => isset($ref['merged']) ? ($this->list($ref['merged'], 200) ?: null) : null,
        ], fn ($value) => $value !== null);
    }

    // Lista de textos cortos, sin vacíos ni repetidos (sin importar mayúsculas).
    private function list(mixed $values, int $max): array
    {
        return collect(is_array($values) ? $values : [$values])
            ->map(fn ($value) => $this->text($value, 50))
            ->filter()
            ->unique(fn ($value) => mb_strtolower($value))
            ->take($max)
            ->values()
            ->all();
    }

    private function seconds(mixed $value): ?int
    {
        return is_numeric($value) && $value >= 0 ? (int) $value : null;
    }

    private function hostIs(string $host, array $domains): bool
    {
        $host = strtolower($host);

        foreach ($domains as $domain) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return true;
            }
        }

        return false;
    }
}
