<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\User;
use App\Services\GenreCategorizer;
use App\Services\InternetArchiveImporter;
use App\Services\YouTubeImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Throwable;

/**
 * Usa la base MongoDB "netfic_testing" (ver phpunit.xml). Se salta si no hay MongoDB disponible.
 */
class ContentApiTest extends TestCase
{
    use RefreshDatabase;

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

    private function contentData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Corazón de Mango',
            'type' => 'novela',
            'source' => 'frutinovela',
            'license' => 'original-ia',
            'genres' => ['Drama'],
            'episodes' => [['season' => 1, 'number' => 1, 'title' => 'Piloto']],
            'published' => true,
        ], $overrides);
    }

    public function test_public_index_only_lists_published_content_and_filters(): void
    {
        Content::create($this->contentData());
        Content::create($this->contentData(['title' => 'Borrador', 'published' => false]));
        Content::create($this->contentData(['title' => 'Nosferatu', 'type' => 'pelicula', 'source' => 'internet_archive', 'license' => 'dominio-publico', 'genres' => ['Terror']]));

        $this->getJson('/api/contents')->assertOk()->assertJsonCount(2, 'data')->assertJsonMissing(['title' => 'Borrador']);
        $this->getJson('/api/contents?type=novela')->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Corazón de Mango');
        $this->getJson('/api/contents?genre=Terror')->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Nosferatu');
        $this->getJson('/api/contents?q=nosfe')->assertJsonCount(1, 'data');
    }

    public function test_show_finds_by_slug_or_id_and_hides_unpublished(): void
    {
        $content = Content::create($this->contentData());
        $draft = Content::create($this->contentData(['title' => 'Borrador', 'published' => false]));

        $this->getJson('/api/contents/corazon-de-mango')->assertOk()->assertJsonPath('episodes.0.title', 'Piloto');
        $this->getJson("/api/contents/{$content->id}")->assertOk()->assertJsonPath('slug', 'corazon-de-mango');
        $this->getJson("/api/contents/{$draft->slug}")->assertNotFound();
    }

    public function test_only_admins_can_write(): void
    {
        $this->postJson('/api/admin/contents', $this->contentData())->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/admin/contents', $this->contentData())->assertForbidden();
    }

    public function test_admin_can_create_update_and_delete(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $id = $this->postJson('/api/admin/contents', $this->contentData())
            ->assertCreated()
            ->assertJsonPath('slug', 'corazon-de-mango')
            ->json('id');

        $this->patchJson("/api/admin/contents/{$id}", ['published' => false])->assertOk()->assertJsonPath('published', false);
        $this->getJson('/api/admin/contents?published=0')->assertJsonCount(1, 'data');

        $this->deleteJson("/api/admin/contents/{$id}")->assertNoContent();
        $this->assertSame(0, Content::count());
    }

    public function test_validation_rejects_unknown_source_and_license(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/admin/contents', $this->contentData(['source' => 'netflix', 'license' => 'copyright']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['source', 'license']);
    }

    public function test_dashboard_sends_published_mongo_content(): void
    {
        Content::create($this->contentData());
        Content::create($this->contentData(['title' => 'Borrador', 'published' => false]));

        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertViewHas('contents', fn ($contents) => $contents->pluck('title')->all() === ['Corazón de Mango'])
            ->assertSee('data-contents=', false);
    }

    public function test_adult_content_is_hidden_from_the_public(): void
    {
        Content::create($this->contentData(['title' => 'Solo adultos', 'maturity' => '18+']));
        Content::create($this->contentData());

        $this->getJson('/api/contents')->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Corazón de Mango');
        $this->getJson('/api/contents/solo-adultos')->assertNotFound();
    }

    public function test_purge_command_deletes_only_adult_content(): void
    {
        Content::create($this->contentData(['title' => 'Sex Madness', 'source' => 'internet_archive', 'license' => 'dominio-publico']));
        Content::create($this->contentData(['title' => 'Diario', 'synopsis' => 'Una reportera entra a una colonia nudista.']));
        Content::create($this->contentData(['title' => 'Sussex Story', 'genres' => ['Comedy']]));
        Content::create($this->contentData());

        $this->artisan('content:purge-adult --dry-run')->assertSuccessful();
        $this->assertSame(4, Content::count());

        $this->artisan('content:purge-adult --force')->assertSuccessful();
        $this->assertEqualsCanonicalizing(['Sussex Story', 'Corazón de Mango'], Content::pluck('title')->all());
    }

    public function test_import_skips_adult_material_and_categorizes_genres(): void
    {
        Http::fake([
            'archive.org/metadata/drsex' => Http::response([
                'metadata' => ['title' => 'Dr. Sex', 'subject' => 'nudie cutie; stripping'],
                'files' => [['name' => 'a.mp4', 'format' => 'h.264', 'length' => '4000']],
            ]),
            'archive.org/metadata/house' => Http::response([
                'metadata' => ['title' => 'House on Haunted Hill', 'subject' => 'Drama; Horror;pdmovies', 'collection' => ['feature_films']],
                'files' => [['name' => 'h.mp4', 'format' => 'h.264', 'length' => '4500']],
            ]),
        ]);

        $importer = app(InternetArchiveImporter::class);

        $this->assertNull($importer->import('drsex'));
        $this->assertSame('material +18', $importer->skipReason());
        $house = $importer->import('house');
        $this->assertSame(['Drama', 'Terror'], $house->genres);
        $this->assertSame(['Drama', 'Horror'], $house->tags);
        $this->assertSame('16+', $house->maturity);
        $this->assertSame(1, Content::count());
    }

    public function test_categorizer_maps_free_tags_to_the_genre_catalog(): void
    {
        $categorizer = new GenreCategorizer;

        $this->assertSame(['Western', 'Comedia'], $categorizer->categorize(['John Wayne', 'Comedy', 'Western']));
        $this->assertSame(['Drama', 'Cine negro', 'Misterio', 'Thriller'], $categorizer->categorize(['Drama', 'Film-Noir', 'Mystery', 'Thriller']));
        $this->assertSame(['Comedia'], $categorizer->categorize([], 'Charlie Chaplin Festival'));
        $this->assertSame([], $categorizer->categorize(['warm weather'], 'Untitled'));
        $this->assertSame('TP', $categorizer->estimateMaturity(['Animación']));
        $this->assertSame('16+', $categorizer->estimateMaturity(['Comedia', 'Terror']));
    }

    public function test_index_filters_by_genres_years_and_sorts(): void
    {
        Content::create($this->contentData(['title' => 'Vieja', 'genres' => ['Western'], 'year' => 1952]));
        Content::create($this->contentData(['title' => 'Media', 'genres' => ['Comedia', 'Western'], 'year' => 1963, 'tags' => ['John Wayne']]));
        Content::create($this->contentData(['title' => 'Nueva', 'genres' => ['Drama'], 'year' => 2026]));

        $this->getJson('/api/contents?genre=Western,Drama&sort=oldest')
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.title', 'Vieja')
            ->assertJsonPath('data.2.title', 'Nueva');
        $this->getJson('/api/contents?genre=Comedia')->assertJsonCount(1, 'data');
        $this->getJson('/api/contents?year_from=1950&year_to=1969&sort=year')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Media');
        $this->getJson('/api/contents?q=wayne')->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Media');

        $this->getJson('/api/contents/genres')->assertOk()->assertExactJson([
            ['genre' => 'Comedia', 'count' => 1],
            ['genre' => 'Drama', 'count' => 1],
            ['genre' => 'Western', 'count' => 2],
        ]);
    }

    public function test_admin_genres_must_come_from_the_catalog(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/admin/contents', $this->contentData(['genres' => ['Comedy']]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['genres.0']);
    }

    private function youtubeVideo(array $overrides = []): array
    {
        return array_merge([
            'id' => 'nceBng2uW_Q',
            'title' => '💔 EL AMOR QUE NUNCA DEBIÓ EXISTIR 😭 | FRUTINOVELA COMPLETA 🍎🍑 FRUTI NOVELA🥦 HISTORIAS DE FRUTAS',
            'description' => "Eric y Beatriz vivían una historia de amor.\n\n👍 Dale LIKE y SUSCRÍBETE\n#Frutinovela https://x.com",
            'duration' => 600,
            'channel' => 'Fruti Novelas',
            'channel_url' => 'https://www.youtube.com/channel/UCpZ',
            'url' => 'https://www.youtube.com/watch?v=nceBng2uW_Q',
            'embed_url' => 'https://www.youtube-nocookie.com/embed/nceBng2uW_Q',
            'thumbnail' => 'https://i.ytimg.com/vi/nceBng2uW_Q/hqdefault.jpg',
            'tags' => ['frutinovelas', 'drama animado', 'historia de frutas', 'comedia corta'],
            'upload_date' => '20261024',
        ], $overrides);
    }

    public function test_youtube_video_is_imported_as_an_embedded_frutinovela(): void
    {
        $importer = app(YouTubeImporter::class);
        $importer->importVideo($this->youtubeVideo());
        $content = $importer->importVideo($this->youtubeVideo());

        $this->assertSame(1, Content::count());
        $this->assertSame('El amor que nunca debió existir', $content->title);
        $this->assertSame('Eric y Beatriz vivían una historia de amor.', $content->synopsis);
        $this->assertSame(['Drama', 'Animación', 'Comedia'], $content->genres);
        $this->assertSame('youtube-embed', $content->license);
        $this->assertSame('frutinovela', $content->source);
        $this->assertNull($content->video_url);
        $this->assertSame('https://www.youtube-nocookie.com/embed/nceBng2uW_Q', $content->embed_url);
        $this->assertSame('Fruti Novelas', $content->source_ref['channel']);
        $this->assertSame(10, $content->duration);
        $this->assertSame(2026, $content->year);
        $this->assertSame('corto', $content->type);
        $this->assertSame([], $content->episodes);
    }

    public function test_long_video_without_chapters_is_split_into_even_episodes(): void
    {
        // 1 hora sin escenas marcadas: 8 capítulos de 7.5 minutos sobre el mismo video.
        $content = app(YouTubeImporter::class)->importVideo($this->youtubeVideo([
            'title' => '1 HORA DE FRUTINOVELAS EN ESPAÑOL',
            'duration' => 3600,
        ]));

        $this->assertSame('novela', $content->type);
        $this->assertNull($content->embed_url);
        $this->assertCount(8, $content->episodes);
        $this->assertSame([0, 450], [$content->episodes[0]['start'], $content->episodes[0]['end']]);
        $this->assertSame(3600, $content->episodes[7]['end']);
        $this->assertSame('Parte 1', $content->episodes[0]['title']);
        $this->assertSame('nceBng2uW_Q', $content->episodes[0]['video_id']);
    }

    public function test_long_video_chapters_are_grouped_and_huge_scenes_split(): void
    {
        $importer = app(YouTubeImporter::class);

        // Escenas de 3 minutos: se juntan de a 3 (9 min); el final de 2 minutos se une al anterior.
        $scenes = array_map(fn ($i) => ['title' => "Escena {$i}", 'start' => $i * 180, 'end' => min(1200, ($i + 1) * 180)], range(0, 6));
        $parts = $importer->segments(['duration' => 1200, 'chapters' => $scenes]);
        $this->assertSame([[0, 540], [540, 1200]], array_map(fn ($p) => [$p['start'], $p['end']], $parts));
        $this->assertSame(['Escena 0', 'Escena 3'], array_column($parts, 'title'));

        // Siete escenas cortas y una última de 54 minutos: esa también se divide.
        $parts = $importer->segments(['duration' => 3705, 'chapters' => [
            ['title' => '¿Un sueño?', 'start' => 0, 'end' => 433],
            ['title' => 'El baile', 'start' => 433, 'end' => 3705],
        ]]);
        $this->assertGreaterThan(6, count($parts));
        $this->assertSame('¿Un sueño?', $parts[0]['title']);
        $this->assertSame(3705, end($parts)['end']);
    }

    public function test_playlist_in_reverse_order_is_put_in_story_order_with_clean_episode_titles(): void
    {
        $content = app(YouTubeImporter::class)->importPlaylist(
            ['id' => 'PLcereza', 'title' => '🍒 Cereza | Frutinovela Completa (Temporada 2)', 'channel' => 'Telefruta'],
            [
                $this->youtubeVideo(['id' => 'c3', 'title' => 'Cereza capítulo Final | Todo llega a su final… #telefruta', 'duration' => 180]),
                $this->youtubeVideo(['id' => 'c2', 'title' => 'Cereza capítulo 2 | Una boda sin novio... #frutinovela', 'duration' => 180]),
                $this->youtubeVideo(['id' => 'c1', 'title' => 'capitulo 1, Fruti traición', 'duration' => 180]),
            ],
        );

        $this->assertSame('Cereza', $content->title);
        $this->assertSame(
            ['Fruti traición', 'Una boda sin novio', 'Todo llega a su final'],
            array_column($content->episodes, 'title'),
        );
        $this->assertSame([2, 2, 2], array_column($content->episodes, 'season'));
        $this->assertSame('c1', $content->episodes[0]['video_id']);
    }

    public function test_youtube_titles_are_cleaned_without_breaking_text(): void
    {
        $importer = app(YouTubeImporter::class);

        $this->assertSame('¡Mi mejor amiga me traiciono!', $importer->cleanTitle('😱 ¡MI MEJOR AMIGA ME TRAICIONO! | FRUTINOVELA'));
        $this->assertSame('1 hora de frutinovelas en español', $importer->cleanTitle('1 HORA DE FRUTINOVELAS EN ESPAÑOL 🍓'));
        $this->assertSame('Mandy conoció al mango de sus sueños', $importer->cleanTitle('MANDY CONOCIÓ AL MANGO DE SUS SUEÑOS 🥭❤️😂 FRUTINOVELAS #frutinovelas'));
        $this->assertSame('¡No hagas más scroll! 1 hora de frutinovelas completas en español para ver tu tv', $importer->cleanTitle('😱😂 ¡NO HAGAS MÁS SCROLL! 🍿 1 HORA de FRUTINOVELAS Completas en Español para ver tu TV'));
    }

    public function test_youtube_adult_videos_are_skipped(): void
    {
        $importer = app(YouTubeImporter::class);

        $this->assertNull($importer->importVideo($this->youtubeVideo(['title' => 'La fresa sexy | FRUTINOVELA'])));
        $this->assertSame('material +18', $importer->skipReason());
        $this->assertSame(0, Content::count());
    }

    public function test_youtube_playlist_becomes_a_novela_with_episodes(): void
    {
        $content = app(YouTubeImporter::class)->importPlaylist(
            ['id' => 'PL123', 'title' => 'Corazón de Piña | FRUTINOVELAS', 'channel' => 'Fruti Novelas', 'url' => 'https://www.youtube.com/playlist?list=PL123'],
            [
                $this->youtubeVideo(['id' => 'a1', 'title' => 'Capítulo 1: El encuentro', 'embed_url' => 'https://www.youtube-nocookie.com/embed/a1']),
                $this->youtubeVideo(['id' => 'a2', 'title' => 'Capítulo 2: Desnuda la verdad']),
                $this->youtubeVideo(['id' => 'a3', 'title' => 'Capítulo 3: La boda', 'embed_url' => 'https://www.youtube-nocookie.com/embed/a3']),
            ],
        );

        $this->assertSame('Corazón de Piña', $content->title);
        $this->assertCount(2, $content->episodes);
        $this->assertSame([1, 2], array_column($content->episodes, 'number'));
        $this->assertSame('https://www.youtube-nocookie.com/embed/a3', $content->episodes[1]['embed_url']);
    }

    public function test_importing_a_playlist_does_not_also_create_its_videos_as_shorts(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'yt');
        file_put_contents($path, json_encode([
            'playlist' => ['id' => 'PLabc', 'title' => 'Amor de Mango Temporada 1', 'channel' => 'AMOR DE MANGO'],
            'items' => [
                $this->youtubeVideo(['id' => 'v1', 'title' => 'EP 1: ¿Linda qué?', 'duration' => 90]),
                $this->youtubeVideo(['id' => 'v2', 'title' => 'EP 2: Se delató cantando', 'duration' => 90]),
            ],
            'skipped' => [],
        ]));

        $this->artisan('content:import-youtube', ['--file' => $path])->assertSuccessful();
        unlink($path);

        $this->assertSame(1, Content::count());
        $this->assertSame('Amor de Mango', Content::first()->title);
        $this->assertSame(['¿Linda qué?', 'Se delató cantando'], array_column(Content::first()->episodes, 'title'));
    }

    public function test_import_command_reads_a_simple_exported_json(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'yt');
        file_put_contents($path, json_encode([[
            'id' => 'xyz', 'title' => 'La venganza de la sandía', 'duration' => 600,
            'embedUrl' => 'https://www.youtube-nocookie.com/embed/xyz', 'thumbnail' => 'https://i.ytimg.com/vi/xyz/hqdefault.jpg',
        ]]));

        $this->artisan('content:import-youtube', ['--file' => $path])->assertSuccessful();
        unlink($path);

        $this->assertSame('https://www.youtube-nocookie.com/embed/xyz', Content::first()->embed_url);
    }

    public function test_internet_archive_import_is_idempotent_and_holds_unknown_licenses(): void
    {
        Http::fake([
            'archive.org/metadata/nosferatu' => Http::response([
                'metadata' => ['title' => 'Nosferatu', 'year' => '1922', 'collection' => ['feature_films'], 'subject' => ['Terror']],
                'files' => [
                    ['name' => 'nosferatu.ogv', 'format' => 'Ogg Video'],
                    ['name' => 'nosferatu 512kb.mp4', 'format' => '512Kb MPEG4', 'length' => '5640.5'],
                ],
            ]),
            'archive.org/metadata/raro' => Http::response([
                'metadata' => ['title' => 'Raro', 'licenseurl' => 'http://creativecommons.org/licenses/by-nc/3.0/', 'collection' => ['opensource_movies']],
                'files' => [['name' => 'raro.mp4', 'format' => 'h.264', 'length' => '00:12:00']],
            ]),
        ]);

        $importer = app(InternetArchiveImporter::class);
        $importer->import('nosferatu');
        $movie = $importer->import('nosferatu');
        $short = $importer->import('raro');

        $this->assertSame(2, Content::count());
        $this->assertSame('pelicula', $movie->type);
        $this->assertSame(94, $movie->duration);
        $this->assertSame(1922, $movie->year);
        $this->assertSame('dominio-publico', $movie->license);
        $this->assertTrue($movie->published);
        $this->assertSame('https://archive.org/download/nosferatu/nosferatu%20512kb.mp4', $movie->video_url);

        $this->assertSame('corto', $short->type);
        $this->assertSame('desconocida', $short->license);
        $this->assertFalse($short->published);
    }
}
