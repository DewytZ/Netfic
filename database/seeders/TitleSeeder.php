<?php

namespace Database\Seeders;

use App\Models\Title;
use Illuminate\Database\Seeder;

class TitleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Limpiamos la tabla primero por si acaso
        Title::truncate();

        // 2. Lista completa con las 30 películas y series de Netfic
        $titles = [
            // Películas
            ['title' => 'La Última Frontera', 'type' => 'Película', 'genre' => 'Ciencia ficción', 'year' => 2025, 'duration' => '2 h 08 min', 'maturity' => '13+', 'description' => 'Una tripulación varada en el borde del sistema solar recibe una señal que no debería existir.', 'colors' => ['#1e3a8a', '#7c3aed']],
            ['title' => 'Ecos del Silencio', 'type' => 'Película', 'genre' => 'Drama', 'year' => 2023, 'duration' => '1 h 54 min', 'maturity' => '13+', 'description' => 'Tras perder la audición, una pianista reconstruye su vida nota por nota.', 'colors' => ['#334155', '#0f172a']],
            ['title' => 'Neón Nocturno', 'type' => 'Película', 'genre' => 'Thriller', 'year' => 2024, 'duration' => '1 h 47 min', 'maturity' => '16+', 'description' => 'Un repartidor nocturno se convierte en testigo del golpe más grande de la ciudad.', 'colors' => ['#db2777', '#4c1d95']],
            ['title' => 'El Faro', 'type' => 'Película', 'genre' => 'Misterio', 'year' => 2022, 'duration' => '1 h 39 min', 'maturity' => '16+', 'description' => 'Dos guardianes de un faro aislado descubren que la isla guarda un secreto.', 'colors' => ['#0e7490', '#082f49']],
            ['title' => 'Marea Roja', 'type' => 'Película', 'genre' => 'Acción', 'year' => 2025, 'duration' => '2 h 01 min', 'maturity' => '13+', 'description' => 'Un equipo de rescate tiene doce horas para sacar a un pueblo entero antes del huracán.', 'colors' => ['#b91c1c', '#450a0a']],
            ['title' => 'Ciudad de Cristal', 'type' => 'Película', 'genre' => 'Ciencia ficción', 'year' => 2024, 'duration' => '1 h 58 min', 'maturity' => '13+', 'description' => 'En una ciudad donde todo es visible, una hacker busca el único punto ciego.', 'colors' => ['#0891b2', '#312e81']],
            ['title' => 'Órbita', 'type' => 'Película', 'genre' => 'Aventura', 'year' => 2023, 'duration' => '1 h 44 min', 'maturity' => '7+', 'description' => 'Una ingeniera y un robot de mantenimiento intentan volver a casa desde una estación abandonada.', 'colors' => ['#f59e0b', '#7c2d12']],
            ['title' => 'Los Olvidados', 'type' => 'Película', 'genre' => 'Terror', 'year' => 2024, 'duration' => '1 h 36 min', 'maturity' => '18+', 'description' => 'Una familia hereda una casa en la que nadie recuerda haber vivido.', 'colors' => ['#3f3f46', '#18181b']],
            ['title' => 'Tormenta Solar', 'type' => 'Película', 'genre' => 'Acción', 'year' => 2025, 'duration' => '2 h 12 min', 'maturity' => '13+', 'description' => 'Una llamarada apaga el planeta y un piloto retirado debe cruzar el continente.', 'colors' => ['#ea580c', '#7f1d1d']],
            ['title' => 'El Jardín Secreto', 'type' => 'Película', 'genre' => 'Fantasía', 'year' => 2022, 'duration' => '1 h 41 min', 'maturity' => '7+', 'description' => 'Una niña encuentra una puerta en el jardín de su abuela que lleva a otro mundo.', 'colors' => ['#15803d', '#064e3b']],
            ['title' => 'Código Cero', 'type' => 'Película', 'genre' => 'Thriller', 'year' => 2024, 'duration' => '1 h 52 min', 'maturity' => '16+', 'description' => 'Un programador descubre que el algoritmo que escribió puede predecir crímenes.', 'colors' => ['#4f46e5', '#020617']],
            ['title' => 'Desierto Infinito', 'type' => 'Película', 'genre' => 'Aventura', 'year' => 2021, 'duration' => '1 h 49 min', 'maturity' => '13+', 'description' => 'Tres amigos cruzan el desierto en busca de una ciudad perdida.', 'colors' => ['#d97706', '#78350f']],
            ['title' => 'Sombras de Tokio', 'type' => 'Película', 'genre' => 'Crimen', 'year' => 2023, 'duration' => '2 h 04 min', 'maturity' => '16+', 'description' => 'Una detective sigue el rastro de un ladrón que nunca aparece en las cámaras.', 'colors' => ['#be123c', '#1e1b4b']],
            ['title' => 'La Casa del Lago', 'type' => 'Película', 'genre' => 'Romance', 'year' => 2022, 'duration' => '1 h 46 min', 'maturity' => '7+', 'description' => 'Dos desconocidos se escriben cartas desde la misma casa, separados por diez años.', 'colors' => ['#0d9488', '#134e4a']],
            ['title' => 'Viaje al Norte', 'type' => 'Película', 'genre' => 'Documental', 'year' => 2024, 'duration' => '1 h 28 min', 'maturity' => 'TP', 'description' => 'Un recorrido por los últimos glaciares de América y las personas que viven junto a ellos.', 'colors' => ['#64748b', '#1e293b']],
            ['title' => 'Pulso', 'type' => 'Película', 'genre' => 'Thriller', 'year' => 2025, 'duration' => '1 h 43 min', 'maturity' => '16+', 'description' => 'Un cirujano tiene una noche para salvar al hombre que arruinó su carrera.', 'colors' => ['#e11d48', '#4a044e']],
            ['title' => 'Horizonte', 'type' => 'Película', 'genre' => 'Drama', 'year' => 2023, 'duration' => '1 h 57 min', 'maturity' => '7+', 'description' => 'Un padre y su hijo reconstruyen su relación durante un viaje en velero.', 'colors' => ['#6366f1', '#1e1b4b']],
            ['title' => 'Fuego Lento', 'type' => 'Película', 'genre' => 'Romance', 'year' => 2024, 'duration' => '1 h 51 min', 'maturity' => '13+', 'description' => 'Una chef y un crítico gastronómico se enamoran en el peor momento posible.', 'colors' => ['#f97316', '#831843']],
            ['title' => 'Medianoche', 'type' => 'Película', 'genre' => 'Misterio', 'year' => 2025, 'duration' => '1 h 48 min', 'maturity' => '13+', 'description' => 'Cada noche a las 12, un pueblo entero olvida el día anterior. Menos una persona.', 'colors' => ['#1d4ed8', '#030712']],
            ['title' => 'Laberinto', 'type' => 'Película', 'genre' => 'Fantasía', 'year' => 2023, 'duration' => '2 h 06 min', 'maturity' => '13+', 'description' => 'Un cartógrafo queda atrapado en un laberinto que cambia cada vez que lo dibuja.', 'colors' => ['#a855f7', '#3b0764']],

            // Series
            ['title' => 'Distrito Norte', 'type' => 'Serie', 'genre' => 'Crimen', 'year' => 2025, 'duration' => '3 temporadas', 'maturity' => '16+', 'description' => 'Una unidad policial enfrenta a la red que controla el puerto más grande del país.', 'colors' => ['#7f1d1d', '#111827']],
            ['title' => 'Estación Polar', 'type' => 'Serie', 'genre' => 'Ciencia ficción', 'year' => 2024, 'duration' => '2 temporadas', 'maturity' => '13+', 'description' => 'Doce científicos, una base en la Antártida y algo que despertó bajo el hielo.', 'colors' => ['#0369a1', '#0c4a6e']],
            ['title' => 'Los Herederos', 'type' => 'Serie', 'genre' => 'Drama', 'year' => 2023, 'duration' => '4 temporadas', 'maturity' => '16+', 'description' => 'La muerte del patriarca desata una guerra entre hermanos por el imperio familiar.', 'colors' => ['#854d0e', '#1c1917']],
            ['title' => 'Señal Perdida', 'type' => 'Serie', 'genre' => 'Misterio', 'year' => 2025, 'duration' => '1 temporada', 'maturity' => '13+', 'description' => 'Un podcast sobre una desaparición antigua empieza a recibir llamadas del desaparecido.', 'colors' => ['#4338ca', '#0f172a']],
            ['title' => 'Reino de Ceniza', 'type' => 'Serie', 'genre' => 'Fantasía', 'year' => 2024, 'duration' => '2 temporadas', 'maturity' => '16+', 'description' => 'Cinco reinos, un trono vacío y una profecía que nadie quiere cumplir.', 'colors' => ['#9a3412', '#292524']],
            ['title' => 'Turno de Noche', 'type' => 'Serie', 'genre' => 'Drama', 'year' => 2022, 'duration' => '5 temporadas', 'maturity' => '13+', 'description' => 'Las historias de un hospital público cuando la ciudad duerme.', 'colors' => ['#0f766e', '#042f2e']],
            ['title' => 'Velocidad Máxima', 'type' => 'Serie', 'genre' => 'Acción', 'year' => 2025, 'duration' => '2 temporadas', 'maturity' => '16+', 'description' => 'Un equipo de pilotos callejeros es reclutado para robar a quienes nadie puede tocar.', 'colors' => ['#dc2626', '#1f2937']],
            ['title' => 'Planeta Azul', 'type' => 'Serie', 'genre' => 'Documental', 'year' => 2023, 'duration' => '1 temporada', 'maturity' => 'TP', 'description' => 'Las criaturas más extrañas del océano profundo, filmadas por primera vez.', 'colors' => ['#0284c7', '#082f49']],
            ['title' => 'La Mansión', 'type' => 'Serie', 'genre' => 'Terror', 'year' => 2024, 'duration' => '2 temporadas', 'maturity' => '18+', 'description' => 'Seis influencers aceptan pasar un mes en una mansión con una historia oscura.', 'colors' => ['#52525b', '#09090b']],
            ['title' => 'Amor en Línea', 'type' => 'Serie', 'genre' => 'Romance', 'year' => 2025, 'duration' => '1 temporada', 'maturity' => '13+', 'description' => 'Una app de citas empareja a dos personas que se odian en la vida real.', 'colors' => ['#db2777', '#500724']],
        ];

        // 3. Insertamos cada título en Supabase
        foreach ($titles as $data) {
            Title::create($data);
        }
    }
}