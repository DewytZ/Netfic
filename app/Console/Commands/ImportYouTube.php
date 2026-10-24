<?php

namespace App\Console\Commands;

use App\Models\Content;
use App\Services\YouTubeImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Importa frutinovelas de YouTube (solo metadatos; se reproducen con el reproductor oficial).
 * Requiere Python con yt-dlp: pip install yt-dlp
 *
 *   php artisan content:import-youtube                                   (busca "frutinovelas completas")
 *   php artisan content:import-youtube "frutinovela de amor" --max=20
 *   php artisan content:import-youtube --series --max=5                  (series reales: listas del canal con capítulos)
 *   php artisan content:import-youtube --playlist="https://www.youtube.com/playlist?list=..."
 *   php artisan content:import-youtube --refresh                         (vuelve a procesar los videos ya importados)
 *   php artisan content:import-youtube --file=frutinovelas.json          (JSON ya exportado)
 */
class ImportYouTube extends Command
{
    protected $signature = 'content:import-youtube
        {query? : Búsqueda en YouTube (por defecto "frutinovelas completas", o "frutinovela capitulos" con --series)}
        {--max=10 : Cuántos videos (o series, con --series) traer}
        {--series : Buscar series reales (listas de reproducción del canal con capítulos numerados)}
        {--max-episodes=40 : Máximo de capítulos por serie}
        {--playlist= : URL de una lista: se importa como una novela con capítulos}
        {--refresh : Volver a procesar los videos sueltos ya importados (aplica la segmentación)}
        {--file= : Importar un JSON exportado antes con scripts/youtube_catalog.py}';

    protected $description = 'Importa frutinovelas desde YouTube (metadatos + reproductor insertado)';

    public function handle(YouTubeImporter $importer): int
    {
        try {
            $data = $this->option('file') ? $this->readFile($this->option('file')) : $this->runScript();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($data['skipped'] ?? [] as $skipped) {
            $this->line("<fg=yellow>descartado</> {$skipped['title']} ({$skipped['reason']})");
        }

        $playlists = $data['series'] ?? [];

        if (! empty($data['playlist'])) {
            $playlists[] = $data;
        }

        foreach ($playlists as $series) {
            $content = $importer->importPlaylist($series['playlist'], $series['items']);
            $content
                ? $this->line("<fg=green>serie</> {$content->title} <fg=gray>[".($content->source_ref['channel'] ?? '?').'] '.count($content->episodes).' capítulos</>')
                : $this->line("<fg=yellow>serie descartada</> {$series['playlist']['title']} ({$importer->skipReason()})");
        }

        // Con --playlist o --series los videos ya quedaron como capítulos: no se importan además sueltos.
        if ($playlists) {
            return self::SUCCESS;
        }

        $imported = 0;

        foreach ($data['items'] as $video) {
            $content = $importer->importVideo($video);

            if (! $content) {
                $this->line("<fg=yellow>omitido</> {$video['title']} ({$importer->skipReason()})");

                continue;
            }

            $imported++;
            $channel = $content->source_ref['channel'] ?? 'canal sin nombre';
            $parts = $content->episodes ? ' '.count($content->episodes).' capítulos' : '';
            $this->line("<fg=green>publicado</> {$content->title} <fg=gray>[{$channel}]{$parts}</>");
        }

        $this->info("Importadas {$imported} frutinovelas.");

        return self::SUCCESS;
    }

    private function runScript(): array
    {
        $args = match (true) {
            (bool) $this->option('playlist') => ['--playlist', $this->option('playlist'), '--max-episodes', (string) $this->option('max-episodes')],
            $this->option('series') => [
                '--series', $this->argument('query') ?? 'frutinovela capitulos',
                '--max', (string) $this->option('max'),
                '--max-episodes', (string) $this->option('max-episodes'),
            ],
            $this->option('refresh') => ['--videos', ...$this->importedVideoIds()],
            default => ['--search', $this->argument('query') ?? 'frutinovelas completas', '--max', (string) $this->option('max')],
        };

        $this->line('Consultando YouTube con yt-dlp…');

        $result = Process::timeout(900)
            ->env(['PYTHONIOENCODING' => 'utf-8'])
            ->run([config('services.youtube.python'), base_path('scripts/youtube_catalog.py'), ...$args]);

        if ($result->failed()) {
            $hint = str_contains($result->errorOutput(), 'yt_dlp') ? ' Instala yt-dlp con: pip install yt-dlp' : '';

            throw new \RuntimeException('Falló scripts/youtube_catalog.py: '.trim($result->errorOutput()).$hint);
        }

        return json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR);
    }

    // Videos sueltos ya importados (las series se identifican por el ID de la lista, no se incluyen).
    private function importedVideoIds(): array
    {
        $ids = Content::where('source', Content::SOURCE_FRUTINOVELA)
            ->get(['source_ref'])
            ->pluck('source_ref.identifier')
            ->filter(fn ($id) => is_string($id) && preg_match('/^[\w-]{11}$/', $id))
            ->values()
            ->all();

        if (! $ids) {
            throw new \RuntimeException('No hay videos de YouTube importados para actualizar.');
        }

        return $ids;
    }

    // Acepta el formato de youtube_catalog.py o una lista simple [{id, title, duration, embedUrl, thumbnail}].
    private function readFile(string $path): array
    {
        $data = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        if (array_is_list($data)) {
            $data = ['playlist' => null, 'skipped' => [], 'items' => array_map(fn ($v) => $v + [
                'embed_url' => $v['embedUrl'] ?? null,
            ], $data)];
        }

        return $data;
    }
}
