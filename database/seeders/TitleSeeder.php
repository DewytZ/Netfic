<?php

namespace Database\Seeders;

use App\Models\Title;
use Illuminate\Database\Seeder;

/**
 * Títulos de la tabla "titles" (catálogo SQL que se administra desde /admin).
 */
class TitleSeeder extends Seeder
{
    private const TITLES = [
        [
            'title' => 'La Última Frontera',
            'type' => 'Película',
            'genre' => 'Ciencia ficción',
            'year' => 2024,
            'duration' => '2 h 8 min',
            'seasons' => null,
            'maturity' => '13+',
            'description' => 'Una tripulación viaja al borde del sistema solar para investigar una señal que nadie sabe explicar.',
            'colors' => ['#0f172a', '#1e40af', '#38bdf8'],
        ],
        [
            'title' => 'Sombras de la Ciudad',
            'type' => 'Serie',
            'genre' => 'Crimen',
            'year' => 2023,
            'duration' => null,
            'seasons' => 2,
            'maturity' => '16+',
            'description' => 'Una detective novata destapa una red de corrupción que llega hasta su propia comisaría.',
            'colors' => ['#111827', '#7f1d1d', '#f59e0b'],
        ],
        [
            'title' => 'El Bosque Encantado',
            'type' => 'Película',
            'genre' => 'Fantasía',
            'year' => 2022,
            'duration' => '1 h 42 min',
            'seasons' => null,
            'maturity' => 'TP',
            'description' => 'Dos hermanos se pierden en un bosque donde los árboles hablan y el tiempo corre al revés.',
            'colors' => ['#052e16', '#15803d', '#a3e635'],
        ],
        [
            'title' => 'Corazones en Pausa',
            'type' => 'Serie',
            'genre' => 'Romance',
            'year' => 2025,
            'duration' => null,
            'seasons' => 1,
            'maturity' => '13+',
            'description' => 'Dos desconocidos comparten departamento por un error de la inmobiliaria y ninguno quiere irse.',
            'colors' => ['#4c0519', '#be185d', '#fda4af'],
        ],
        [
            'title' => 'Noche Sin Fin',
            'type' => 'Película',
            'genre' => 'Terror',
            'year' => 2021,
            'duration' => '1 h 35 min',
            'seasons' => null,
            'maturity' => '16+',
            'description' => 'En un pueblo aislado el sol deja de salir y los vecinos empiezan a desaparecer uno a uno.',
            'colors' => ['#09090b', '#3f3f46', '#dc2626'],
        ],
        [
            'title' => 'Rutas del Pacífico',
            'type' => 'Serie',
            'genre' => 'Aventura',
            'year' => 2024,
            'duration' => null,
            'seasons' => 3,
            'maturity' => '7+',
            'description' => 'Un grupo de amigos recorre la costa en una camioneta vieja buscando el mapa de un tesoro familiar.',
            'colors' => ['#082f49', '#0e7490', '#fde68a'],
        ],
    ];

    public function run(): void
    {
        foreach (self::TITLES as $title) {
            Title::updateOrCreate(['title' => $title['title']], $title);
        }

        $this->command?->info(count(self::TITLES).' títulos en la tabla titles.');
    }
}
