<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Services\ContentDeduplicator;
use App\Services\YouTubeImporter;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Throwable;

/**
 * Sanitización del catálogo y quitado de frutinovelas duplicadas.
 * Usa la base MongoDB "netfic_testing" (ver phpunit.xml). Se salta si no hay MongoDB disponible.
 */
class ContentCleanupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        try {
            DB::connection('mongodb')->getMongoDB()->command(['ping' => 1]);
        } catch (Throwable) {
            $this->markTestSkipped('MongoDB no está disponible.');
        }

        Content::truncate();
    }

    private function frutinovela(array $overrides = []): Content
    {
        return Content::create(array_merge([
            'title' => 'Amor de Mango',
            'type' => 'novela',
            'source' => 'frutinovela',
            'license' => 'youtube-embed',
            'published' => true,
            'source_ref' => ['identifier' => 'PL1', 'channel' => 'Fruti Novelas'],
            'episodes' => [
                ['season' => 1, 'number' => 1, 'title' => 'El encuentro', 'video_id' => 'aaaaaaaaaaa'],
                ['season' => 1, 'number' => 2, 'title' => 'La boda', 'video_id' => 'bbbbbbbbbbb'],
            ],
        ], $overrides));
    }

    public function test_text_is_stripped_of_html_and_invisible_characters(): void
    {
        $content = $this->frutinovela([
            'title' => "  <b>Amor</b>\u{200B} de <script>alert(1)</script>Mango\u{202E}  ",
            'synopsis' => "Línea uno &amp; algo <3\n\n\n\n<img src=x onerror=alert(1)>Línea dos",
            'tags' => ['Drama', 'drama', '', '<i>Amor</i>'],
        ]);

        $this->assertSame('Amor de Mango', $content->title);
        $this->assertSame("Línea uno & algo <3\n\nLínea dos", $content->synopsis);
        $this->assertSame(['Drama', 'Amor'], $content->tags);
    }

    public function test_urls_are_validated_and_youtube_links_normalized(): void
    {
        $content = $this->frutinovela([
            'poster_url' => 'javascript:alert(1)',
            'video_url' => 'http://archive.org/download/x/x.mp4',
            'embed_url' => 'https://www.youtube.com/watch?v=nceBng2uW_Q&si=rastreo123',
            'episodes' => [
                ['season' => 1, 'number' => 1, 'title' => 'Uno', 'embed_url' => 'https://youtu.be/aaaaaaaaaaa?t=3', 'thumbnail_url' => 'data:image/png;base64,xx'],
            ],
        ]);

        $this->assertNull($content->poster_url);
        $this->assertSame('https://archive.org/download/x/x.mp4', $content->video_url);
        $this->assertSame('https://www.youtube-nocookie.com/embed/nceBng2uW_Q', $content->embed_url);
        $this->assertSame('aaaaaaaaaaa', $content->episodes[0]['video_id']);
        $this->assertSame('https://www.youtube-nocookie.com/embed/aaaaaaaaaaa', $content->episodes[0]['embed_url']);
        $this->assertArrayNotHasKey('thumbnail_url', $content->episodes[0]);
    }

    public function test_repeated_episodes_are_removed_and_renumbered(): void
    {
        $content = $this->frutinovela(['episodes' => [
            ['season' => 1, 'number' => 3, 'title' => 'Tercero', 'video_id' => 'ccccccccccc'],
            ['season' => 1, 'number' => 1, 'title' => 'Primero', 'video_id' => 'aaaaaaaaaaa'],
            ['season' => 1, 'number' => 2, 'title' => 'Primero otra vez', 'video_id' => 'aaaaaaaaaaa'],
            ['season' => 1, 'number' => 5, 'title' => 'Parte 2', 'video_id' => 'aaaaaaaaaaa', 'start' => 480, 'end' => 960],
        ]]);

        $this->assertSame(['Primero', 'Tercero', 'Parte 2'], array_column($content->episodes, 'title'));
        $this->assertSame([1, 2, 3], array_column($content->episodes, 'number'));
    }

    public function test_same_title_from_same_channel_is_merged(): void
    {
        $keeper = $this->frutinovela();
        $this->frutinovela([
            'title' => '💔 AMOR DE MANGO (Temporada 2)',
            'source_ref' => ['identifier' => 'PL2', 'channel' => 'Fruti Novelas'],
            'episodes' => [
                ['season' => 2, 'number' => 1, 'title' => 'El regreso', 'video_id' => 'ccccccccccc'],
            ],
        ]);

        $report = app(ContentDeduplicator::class)->run();

        $this->assertSame('fusionado', $report[0]['action']);
        $this->assertSame(1, Content::count());
        $keeper->refresh();
        $this->assertSame(['El encuentro', 'La boda', 'El regreso'], array_column($keeper->episodes, 'title'));
        $this->assertSame([1, 1, 2], array_column($keeper->episodes, 'season'));
        $this->assertSame(['PL2'], $keeper->source_ref['merged']);
    }

    public function test_same_title_from_another_channel_is_deleted(): void
    {
        $this->frutinovela();
        $this->frutinovela(['source_ref' => ['identifier' => 'PL9', 'channel' => 'Canal que resube'], 'episodes' => [
            ['season' => 1, 'number' => 1, 'title' => 'Copia', 'video_id' => 'zzzzzzzzzzz'],
        ]]);

        app(ContentDeduplicator::class)->run();

        $this->assertSame(1, Content::count());
        $this->assertSame('PL1', Content::first()->source_ref['identifier']);
    }

    public function test_video_already_inside_a_novela_is_deleted(): void
    {
        $this->frutinovela();
        $this->frutinovela([
            'title' => 'La boda',
            'type' => 'corto',
            'source_ref' => ['identifier' => 'bbbbbbbbbbb'],
            'embed_url' => 'https://www.youtube-nocookie.com/embed/bbbbbbbbbbb',
            'episodes' => [],
        ]);

        $report = app(ContentDeduplicator::class)->run();

        $this->assertSame('borrado', $report[0]['action']);
        $this->assertSame(['Amor de Mango'], Content::pluck('title')->all());
    }

    public function test_channel_labels_and_loose_separators_are_removed_from_titles(): void
    {
        $importer = app(YouTubeImporter::class);

        $this->assertSame('Yo soy fresi la fea', $importer->cleanTitle('YO SOY FRESI LA FEA ( FRUTI NOVELA ) Cap. COMPLETO, 4k'));
        $this->assertSame('40 minutos de frutinovelas', $importer->cleanTitle('FRUTI NOVELA ( HISTORIAs COMPLETA) 40 Minutos'));
        $this->assertSame('El bienestar', $importer->cleanTitle('Frutinovela: el bienestar (HISTORIA COMPLETA)'));

        $series = $importer->importPlaylist(
            ['id' => 'PL5', 'title' => 'FRUTINOVELA 🍓🍊 | Entre Fresas y Celos – Temporada 1.'],
            [['id' => 'eeeeeeeeeee', 'title' => 'Capítulo 1', 'duration' => 300]],
        );
        $this->assertSame('Entre Fresas y Celos', $series->title);

        $this->assertSame('Amor de Mango', $this->frutinovela(['title' => 'Amor de Mango –'])->title);
    }

    public function test_youtube_shorts_are_not_imported(): void
    {
        $importer = app(YouTubeImporter::class);

        $this->assertNull($importer->importVideo(['id' => 'sssssssssss', 'title' => 'La fresa', 'duration' => 40, 'media_type' => 'short']));
        $this->assertSame('es un Short de YouTube', $importer->skipReason());

        $series = $importer->importPlaylist(['id' => 'PL7', 'title' => 'Cereza'], [
            ['id' => 'aaaaaaaaaaa', 'title' => 'Capítulo 1', 'duration' => 200, 'media_type' => 'video'],
            ['id' => 'sssssssssss', 'title' => 'Capítulo 2', 'duration' => 30, 'media_type' => 'short'],
        ]);
        $this->assertSame(['aaaaaaaaaaa'], array_column($series->episodes, 'video_id'));
    }

    public function test_frutinovela_seeder_loads_the_exported_metadata_without_duplicates(): void
    {
        $this->seed(\Database\Seeders\FrutinovelaSeeder::class);
        $count = Content::where('source', 'frutinovela')->count();
        $this->seed(\Database\Seeders\FrutinovelaSeeder::class);

        $this->assertGreaterThan(5, $count);
        $this->assertSame($count, Content::where('source', 'frutinovela')->count());
        $this->assertTrue(Content::where('source', 'frutinovela')->get()->every(fn ($c) => $c->embed_url || $c->episodes));
    }

    public function test_dry_run_changes_nothing(): void
    {
        $this->frutinovela();
        $this->frutinovela(['source_ref' => ['identifier' => 'PL2', 'channel' => 'Fruti Novelas']]);

        $this->artisan('content:clean', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(2, Content::count());
    }

    public function test_importer_skips_videos_that_are_already_episodes_or_merged(): void
    {
        $this->frutinovela(['source_ref' => ['identifier' => 'PL1', 'merged' => ['PL2']]]);
        $importer = app(YouTubeImporter::class);

        $video = ['id' => 'aaaaaaaaaaa', 'title' => 'El encuentro | FRUTINOVELA', 'duration' => 300];
        $this->assertNull($importer->importVideo($video));
        $this->assertStringContainsString('Amor de Mango', $importer->skipReason());

        $this->assertNull($importer->importPlaylist(['id' => 'PL2', 'title' => 'Amor de Mango'], [['id' => 'ddddddddddd', 'title' => 'Capítulo 1', 'duration' => 300]]));
        $this->assertStringContainsString('fusionado', $importer->skipReason());

        $this->assertSame(1, Content::count());
    }
}
