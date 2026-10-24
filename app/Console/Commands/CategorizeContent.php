<?php

namespace App\Console\Commands;

use App\Models\Content;
use App\Services\GenreCategorizer;
use Illuminate\Console\Command;

/**
 * Pasa los géneros de todo el contenido al catálogo fijo (GenreCategorizer::GENRES).
 * No descarga nada: trabaja con lo guardado. Las etiquetas originales quedan en "tags".
 *
 *   php artisan content:categorize --dry-run
 *   php artisan content:categorize
 */
class CategorizeContent extends Command
{
    protected $signature = 'content:categorize {--dry-run : Solo mostrar los cambios}';

    protected $description = 'Normaliza los géneros del contenido multimedia y estima la clasificación faltante';

    public function handle(GenreCategorizer $categorizer): int
    {
        $rows = [];

        foreach (Content::all() as $content) {
            // Si aún no tiene "tags", sus géneros actuales son las etiquetas originales.
            $tags = $content->tags ?: (array) $content->genres;
            $genres = $categorizer->categorize($tags, $content->title, $content->synopsis);
            $maturity = $content->maturity ?: $categorizer->estimateMaturity($genres);

            $rows[] = [$content->title, implode(', ', (array) $content->genres), implode(', ', $genres) ?: '—', $maturity];

            if (! $this->option('dry-run')) {
                $content->fill(['tags' => $tags, 'genres' => $genres, 'maturity' => $maturity])->save();
            }
        }

        $this->table(['Título', 'Antes', 'Géneros', 'Edad'], $rows);
        $this->info($this->option('dry-run') ? 'Sin cambios (--dry-run).' : 'Contenido categorizado: '.count($rows).'.');

        return self::SUCCESS;
    }
}
