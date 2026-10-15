<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajusta la tabla titles para que funcionen las series:
 * - "seasons" puede no existir en bases creadas antes de añadirla a la migración original.
 * - "duration" debe aceptar NULL, porque las series no tienen duración.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('titles', function (Blueprint $table) {
            if (! Schema::hasColumn('titles', 'seasons')) {
                $table->integer('seasons')->nullable();
            }

            $table->string('duration')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No se revierte: volver a exigir "duration" fallaría con las series ya guardadas.
    }
};
