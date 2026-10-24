<?php

namespace App\Services;

/**
 * Convierte etiquetas libres (los "subject" de Internet Archive, en inglés y sin orden)
 * en el catálogo fijo de géneros en español que usa toda la app.
 */
class GenreCategorizer
{
    // Catálogo de géneros. Los primeros 11 son los que ya usa la tabla titles.
    public const GENRES = [
        'Acción', 'Aventura', 'Ciencia ficción', 'Crimen', 'Documental', 'Drama', 'Fantasía',
        'Misterio', 'Romance', 'Terror', 'Thriller',
        'Animación', 'Bélico', 'Cine negro', 'Comedia', 'Familia', 'Histórico', 'Musical', 'Western',
    ];

    // Inicio de palabra (minúsculas) => género. Incluye inglés y español.
    private const MAP = [
        'action' => 'Acción', 'acción' => 'Acción', 'accion' => 'Acción', 'martial art' => 'Acción', 'drag racing' => 'Acción',
        'adventure' => 'Aventura', 'aventura' => 'Aventura', 'jungle' => 'Aventura', 'tarzan' => 'Aventura', 'swashbuckl' => 'Aventura',
        'sci-fi' => 'Ciencia ficción', 'science fiction' => 'Ciencia ficción', 'ciencia ficción' => 'Ciencia ficción', 'space' => 'Ciencia ficción', 'alien' => 'Ciencia ficción', 'robot' => 'Ciencia ficción', 'dinosaur' => 'Ciencia ficción',
        'crime' => 'Crimen', 'crimen' => 'Crimen', 'gangster' => 'Crimen', 'criminal' => 'Crimen',
        'documentar' => 'Documental', 'documental' => 'Documental', 'educational' => 'Documental', 'newsreel' => 'Documental',
        'drama' => 'Drama', 'melodrama' => 'Drama',
        'fantasy' => 'Fantasía', 'fantasía' => 'Fantasía', 'fairy tale' => 'Fantasía', 'cuento' => 'Fantasía',
        'mystery' => 'Misterio', 'misterio' => 'Misterio', 'sherlock' => 'Misterio', 'detective' => 'Misterio',
        'romance' => 'Romance', 'romantic' => 'Romance', 'romántic' => 'Romance', 'love story' => 'Romance', 'historia de amor' => 'Romance',
        'horror' => 'Terror', 'terror' => 'Terror', 'monster' => 'Terror', 'vampire' => 'Terror', 'zombie' => 'Terror', 'haunted' => 'Terror',
        'thriller' => 'Thriller', 'suspense' => 'Thriller', 'suspenso' => 'Thriller',
        'animat' => 'Animación', 'animación' => 'Animación', 'animacion' => 'Animación', 'animado' => 'Animación', 'cartoon' => 'Animación', 'anime' => 'Animación',
        'war' => 'Bélico', 'bélic' => 'Bélico', 'world war' => 'Bélico', 'nazi' => 'Bélico', 'military' => 'Bélico',
        'film-noir' => 'Cine negro', 'film noir' => 'Cine negro', 'noir' => 'Cine negro', 'cine negro' => 'Cine negro',
        'comedy' => 'Comedia', 'comedia' => 'Comedia', 'comedies' => 'Comedia', 'slapstick' => 'Comedia', 'three stooges' => 'Comedia', 'laurel' => 'Comedia', 'chaplin' => 'Comedia', 'humor' => 'Comedia',
        'family' => 'Familia', 'familia' => 'Familia', 'children' => 'Familia', 'kids' => 'Familia', 'infantil' => 'Familia',
        // "history"/"histórico", no "histor": "historia de frutas" no es cine histórico.
        'history' => 'Histórico', 'historical' => 'Histórico', 'histórico' => 'Histórico', 'historico' => 'Histórico', 'biograph' => 'Histórico', 'biografía' => 'Histórico', 'period drama' => 'Histórico',
        'musical' => 'Musical', 'music' => 'Musical', 'música' => 'Musical',
        'western' => 'Western', 'cowboy' => 'Western', 'john wayne' => 'Western',
    ];

    /**
     * @param  array<int, string>  $tags  etiquetas originales
     * @return array<int, string> géneros del catálogo, sin repetir, en el orden en que aparecen
     */
    public function categorize(array $tags, ?string $title = null, ?string $synopsis = null): array
    {
        // Sin etiquetas útiles probamos con el título ("Charlie Chaplin Festival" => Comedia)
        // y al final con la sinopsis, de la que solo tomamos los 2 primeros géneros (es texto más ruidoso).
        // "Familia" nunca sale de la sinopsis: que hable de niños no la hace apta para niños.
        return $this->match(implode(' | ', $tags))
            ?: $this->match((string) $title)
            ?: array_slice(array_values(array_diff($this->match((string) $synopsis), ['Familia'])), 0, 2);
    }

    /**
     * Clasificación estimada a partir de los géneros, para contenido que no trae una.
     */
    public function estimateMaturity(array $genres): string
    {
        return match (true) {
            (bool) array_intersect($genres, ['Terror', 'Bélico', 'Crimen', 'Thriller', 'Cine negro']) => '16+',
            (bool) array_intersect($genres, ['Drama', 'Misterio', 'Romance', 'Ciencia ficción', 'Acción', 'Documental']) => '13+',
            (bool) array_intersect($genres, ['Comedia', 'Aventura', 'Western', 'Fantasía', 'Histórico']) => '7+',
            (bool) array_intersect($genres, ['Animación', 'Familia', 'Musical']) => 'TP',
            default => '13+',
        };
    }

    private function match(string $text): array
    {
        $text = mb_strtolower($text);
        $found = [];

        foreach (self::MAP as $keyword => $genre) {
            // Las claves cortas van como palabra completa ("war" no debe detectar "warm").
            $end = mb_strlen($keyword) <= 4 ? '\b' : '';

            if (preg_match('/\b'.preg_quote($keyword, '/').$end.'/u', $text, $m, PREG_OFFSET_CAPTURE)) {
                $found[$genre] = min($found[$genre] ?? PHP_INT_MAX, $m[0][1]);
            }
        }

        asort($found);

        return array_keys($found);
    }
}
