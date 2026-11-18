<?php

namespace Database\Seeders;

use App\Models\Content;
use Illuminate\Database\Seeder;
use Throwable;

/**
 * Catálogo multimedia de ejemplo en MongoDB: películas de dominio público de Internet Archive.
 * Los videos no se descargan; se reproducen directo desde archive.org.
 *
 * Para traer más: php artisan content:import-archive --limit=50
 */
class ContentSeeder extends Seeder
{
    // [identificador en archive.org, archivo MP4, título, año, minutos, géneros, clasificación, idioma, licencia, sinopsis]
    private const MOVIES = [
        ['his_girl_friday', 'his_girl_friday.mp4', 'His Girl Friday', 1940, 92, ['Comedia', 'Romance'], '7+', 'English', 'dominio-publico',
            'Un editor de periódico hace lo imposible para que su exesposa, la mejor reportera que tiene, cubra una última gran historia antes de volver a casarse.'],
        ['TheGeneral1926', 'The_General_1926_720p.mp4', 'The General', 1926, 79, ['Comedia', 'Aventura', 'Bélico'], '7+', null, 'dominio-publico',
            'Durante la Guerra Civil estadounidense, un maquinista persigue a los espías que le robaron su locomotora. Clásico mudo de Buster Keaton.'],
        ['CC_1916_10_02_ThePawnshop', 'CC_1916_10_02_ThePawnshop_512kb.mp4', 'The Pawnshop', 1916, 20, ['Comedia'], 'TP', null, 'dominio-publico',
            'Charlie Chaplin trabaja en una casa de empeño y convierte cada objeto que llega al mostrador en un desastre.'],
        ['house_on_haunted_hill_ipod', 'house_on_haunted_hill.mp4', 'House on Haunted Hill', 1959, 75, ['Terror', 'Thriller'], '16+', 'English', 'dominio-publico',
            'Un millonario ofrece diez mil dólares a cinco invitados que logren pasar la noche en una mansión con fama de embrujada.'],
        ['JungleBook', 'Jungle_Book.mp4', 'Jungle Book', 1942, 105, ['Aventura', 'Fantasía', 'Familia'], '7+', 'English', 'dominio-publico',
            'Mowgli, criado por lobos en la selva de la India, vuelve a la aldea de los hombres y descubre que no es tan distinta de la jungla.'],
        ['Sita_Sings_the_Blues', 'SITA_SINGS_MOVIE_ONLY.mp4', 'Sita Sings the Blues', 2008, 82, ['Animación', 'Musical'], '13+', 'English', 'cc0',
            'Una versión animada del Ramayana contada con jazz de los años 20 y una historia de desamor moderna en paralelo.'],
        ['gullivers_travels1939', 'gullivers_travels1939.mp4', "Gulliver's Travels", 1939, 73, ['Animación', 'Familia', 'Aventura'], 'TP', 'English', 'dominio-publico',
            'Tras un naufragio, Gulliver despierta en Liliput, un reino de gente diminuta a punto de entrar en guerra por una canción.'],
        ['ThePhantomoftheOpera', 'Phantom_of_the_Opera_512kb.mp4', 'The Phantom of the Opera', 1925, 106, ['Terror', 'Drama'], '16+', null, 'dominio-publico',
            'Bajo la Ópera de París vive un hombre enmascarado obsesionado con una joven soprano. Lon Chaney en el clásico mudo.'],
        ['lost_world', 'lost_world.mp4', 'The Lost World', 1925, 68, ['Aventura', 'Fantasía', 'Ciencia ficción'], '7+', null, 'dominio-publico',
            'Una expedición viaja a una meseta de Sudamérica donde los dinosaurios siguen vivos.'],
        ['DasKabinettdesDoktorCaligariTheCabinetofDrCaligari', 'The_Cabinet_of_Dr._Caligari_512kb.mp4', 'El gabinete del doctor Caligari', 1919, 51, ['Terror', 'Fantasía'], '16+', 'German', 'dominio-publico',
            'Un hipnotista de feria y su sonámbulo siembran el miedo en un pueblo alemán. Obra clave del expresionismo.'],
        ['TheStranger_0', 'The_Stranger.mp4', 'The Stranger', 1946, 91, ['Cine negro', 'Thriller', 'Misterio'], '16+', 'English', 'dominio-publico',
            'Un investigador de crímenes de guerra sigue la pista de un fugitivo nazi que vive escondido como profesor en un pueblo tranquilo.'],
        ['suddenly', 'suddenly.mp4', 'Suddenly', 1954, 76, ['Crimen', 'Drama'], '16+', 'English', 'dominio-publico',
            'Tres asesinos toman una casa de un pequeño pueblo para disparar al presidente cuando pase su tren. Con Frank Sinatra.'],
        ['my_favorite_brunette', 'my_favorite_brunette.mp4', 'My Favorite Brunette', 1947, 87, ['Comedia', 'Misterio'], '7+', 'English', 'dominio-publico',
            'Un fotógrafo que sueña con ser detective se mete en un caso de verdad por culpa de una misteriosa morena. Con Bob Hope.'],
        ['The_Pied_Piper_of_Hamelin', 'pied.mp4', 'The Pied Piper of Hamelin', 1957, 87, ['Fantasía', 'Musical', 'Familia'], 'TP', 'English', 'dominio-publico',
            'Un flautista libra a Hamelín de las ratas, pero cuando el pueblo no le paga, su música se lleva algo mucho más valioso.'],
    ];

    public function run(): void
    {
        try {
            foreach (self::MOVIES as [$id, $file, $title, $year, $minutes, $genres, $maturity, $language, $license, $synopsis]) {
                $content = Content::where('source', Content::SOURCE_INTERNET_ARCHIVE)
                    ->where('source_ref.identifier', $id)
                    ->first() ?? new Content;

                $content->fill([
                    'title' => $title,
                    'type' => $minutes < 40 ? 'corto' : 'pelicula',
                    'source' => Content::SOURCE_INTERNET_ARCHIVE,
                    'source_ref' => ['identifier' => $id, 'url' => "https://archive.org/details/{$id}"],
                    'license' => $license,
                    'license_url' => $license === 'cc0'
                        ? 'http://creativecommons.org/publicdomain/zero/1.0/'
                        : 'http://creativecommons.org/licenses/publicdomain/',
                    'synopsis' => $synopsis,
                    'genres' => $genres,
                    'tags' => [],
                    'year' => $year,
                    'duration' => $minutes,
                    'language' => $language,
                    'maturity' => $maturity,
                    'poster_url' => "https://archive.org/services/img/{$id}",
                    'video_url' => "https://archive.org/download/{$id}/".rawurlencode($file),
                    'published' => true,
                ])->save();
            }
        } catch (Throwable $e) {
            // Sin MongoDB la app sigue funcionando (el catálogo sale vacío), así que no se corta el resto del seed.
            $this->command?->warn('No se pudo llenar MongoDB: '.$e->getMessage());

            return;
        }

        $this->command?->info(count(self::MOVIES).' contenidos en MongoDB (colección contents).');
    }
}
