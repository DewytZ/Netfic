<?php

namespace App\Console\Commands;

use App\Services\InternetArchiveImporter;
use Illuminate\Console\Command;
use Throwable;

/**
 * Ejemplos:
 *   php artisan content:import-archive                         (top de la colección feature_films)
 *   php artisan content:import-archive "collection:(film_noir)" --limit=10
 *   php artisan content:import-archive --id=night_of_the_living_dead
 */
class ImportInternetArchive extends Command
{
    protected $signature = 'content:import-archive
        {query=collection:(feature_films) : Búsqueda en sintaxis de archive.org}
        {--limit=20 : Cuántos items traer}
        {--id=* : Importar identificadores concretos en vez de buscar}';

    protected $description = 'Importa películas libres de copyright desde Internet Archive a MongoDB';

    public function handle(InternetArchiveImporter $importer): int
    {
        $ids = $this->option('id') ?: $importer->search($this->argument('query'), (int) $this->option('limit'));

        if (! $ids) {
            $this->warn('No se encontraron resultados.');

            return self::SUCCESS;
        }

        $imported = 0;

        foreach ($ids as $id) {
            try {
                $content = $importer->import($id);
            } catch (Throwable $e) {
                $this->error("{$id}: {$e->getMessage()}");

                continue;
            }

            if (! $content) {
                $this->line("<fg=yellow>omitido</> {$id} ({$importer->skipReason()})");

                continue;
            }

            $imported++;
            $state = $content->published ? '<fg=green>publicado</>' : '<fg=yellow>en revisión</>';
            $this->line("{$state} {$content->title} [{$content->license}]");
        }

        $this->info("Importados {$imported} de ".count($ids).'.');

        return self::SUCCESS;
    }
}
