<?php

namespace Database\Seeders;

use App\Services\ContentDeduplicator;
use App\Services\YouTubeImporter;
use Illuminate\Database\Seeder;
use Throwable;

/**
 * Frutinovelas de ejemplo en MongoDB, a partir de metadatos ya exportados con yt-dlp
 * (database/seeders/data/frutinovelas.json). No necesita Python ni internet: solo se guardan
 * metadatos y los videos se ven con el reproductor oficial de YouTube.
 *
 * Pasa por el mismo importador que content:import-youtube: limpieza de títulos, división de los videos
 * largos en capítulos, filtro +18, sin Shorts y sin duplicados. Se puede correr las veces que quieras.
 *
 * Para regenerar el JSON con otros videos:
 *   python scripts/youtube_catalog.py --search "frutinovelas completas" --max 12 --out database/seeders/data/frutinovelas.json
 */
class FrutinovelaSeeder extends Seeder
{
    public function run(YouTubeImporter $importer, ContentDeduplicator $deduplicator): void
    {
        try {
            $data = json_decode(file_get_contents(database_path('seeders/data/frutinovelas.json')), true, flags: JSON_THROW_ON_ERROR);

            $series = 0;
            foreach ($data['series'] ?? [] as $playlist) {
                $importer->importPlaylist($playlist['playlist'], $playlist['items']) && $series++;
            }

            $videos = 0;
            foreach ($data['items'] ?? [] as $video) {
                $importer->importVideo($video) && $videos++;
            }

            $duplicates = count($deduplicator->run());
        } catch (Throwable $e) {
            // Sin MongoDB la app sigue funcionando (el catálogo sale vacío), así que no se corta el resto del seed.
            $this->command?->warn('No se pudieron cargar las frutinovelas: '.$e->getMessage());

            return;
        }

        $total = \App\Models\Content::where('source', \App\Models\Content::SOURCE_FRUTINOVELA)->count();
        $this->command?->info("Frutinovelas: {$total} en el catálogo ({$videos} videos y {$series} series importados, {$duplicates} duplicados fusionados o quitados).");
    }
}
