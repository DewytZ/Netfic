<?php

namespace App\Console\Commands;

use App\Models\Content;
use App\Services\ContentDeduplicator;
use App\Services\ContentSanitizer;
use Illuminate\Console\Command;

/**
 * Limpia el catálogo que ya está guardado:
 *  1. Sanitiza todos los contenidos (texto, URLs y capítulos), de cualquier origen.
 *  2. Quita las frutinovelas duplicadas.
 *
 *   php artisan content:clean --dry-run     (solo muestra lo que cambiaría)
 *   php artisan content:clean
 */
class CleanContent extends Command
{
    protected $signature = 'content:clean
        {--dry-run : Mostrar los cambios sin guardar nada}
        {--no-dedupe : Solo sanitizar, sin quitar duplicados}';

    protected $description = 'Sanitiza el catálogo y quita las frutinovelas duplicadas';

    public function handle(ContentSanitizer $sanitizer, ContentDeduplicator $deduplicator): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $changed = 0;

        foreach (Content::all() as $content) {
            $sanitizer->apply($content);
            $fields = array_keys($content->getDirty());

            if (! $fields) {
                continue;
            }

            $changed++;
            $this->line("<fg=cyan>sanitizado</> {$content->title} <fg=gray>(".implode(', ', $fields).')</>');
            $dryRun || $content->save();
        }

        $this->info("Sanitizados: {$changed}.");

        if (! $this->option('no-dedupe')) {
            $report = $deduplicator->run($dryRun);

            foreach ($report as $entry) {
                $this->line("<fg=yellow>{$entry['action']}</> {$entry['title']} <fg=gray>→ {$entry['into']} ({$entry['reason']})</>");
            }

            $this->info('Duplicados: '.count($report).'.');
        }

        if ($dryRun) {
            $this->warn('Modo --dry-run: no se guardó nada.');
        }

        return self::SUCCESS;
    }
}
