<?php

namespace App\Console\Commands;

use App\Models\Content;
use App\Services\AdultContentFilter;
use Illuminate\Console\Command;

/**
 * Ejemplos:
 *   php artisan content:purge-adult --dry-run   (solo lista lo que borraría)
 *   php artisan content:purge-adult             (borra, pide confirmación)
 *   php artisan content:purge-adult --force     (borra sin preguntar)
 */
class PurgeAdultContent extends Command
{
    protected $signature = 'content:purge-adult
        {--dry-run : Solo mostrar qué se borraría}
        {--force : No pedir confirmación}';

    protected $description = 'Borra de MongoDB el contenido +18 (sexual, abuso o violencia gráfica)';

    public function handle(AdultContentFilter $filter): int
    {
        $adult = Content::all()->filter(
            fn (Content $c) => $filter->isAdult($c->title, $c->genres ?? [], $c->synopsis, $c->maturity)
        );

        if ($adult->isEmpty()) {
            $this->info('No hay contenido +18.');

            return self::SUCCESS;
        }

        $this->table(['Título', 'Origen'], $adult->map(fn ($c) => [$c->title, $c->source])->values());

        if ($this->option('dry-run')) {
            $this->info("Se borrarían {$adult->count()} contenidos.");

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("¿Borrar estos {$adult->count()} contenidos?", true)) {
            return self::FAILURE;
        }

        Content::whereIn('_id', $adult->modelKeys())->delete();
        $this->info("Borrados {$adult->count()} contenidos +18.");

        return self::SUCCESS;
    }
}
