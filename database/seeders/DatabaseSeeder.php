<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Datos de ejemplo para desarrollo. Se puede correr las veces que quieras:
 * cada seeder actualiza lo que ya existe en vez de duplicarlo.
 *
 * Sin WithoutModelEvents a propósito: Content genera su slug en el evento "saving".
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,      // PostgreSQL: usuarios y perfiles
            TitleSeeder::class,     // PostgreSQL: tabla titles
            ContentSeeder::class,   // MongoDB: clásicos de dominio público (Internet Archive)
            FrutinovelaSeeder::class,   // MongoDB: frutinovelas de YouTube (solo metadatos)
        ]);
    }
}
