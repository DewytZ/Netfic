<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('titles', function (Blueprint $table) {
            $table->id();
            
            //columnas para nuestro catalogo de peliculas y series
            $table->string('title');
            $table->string('type')->default('Película');
            $table->string('genre');
            $table->integer('year');
            $table->string('duration');
            $table->text('description')->nullable();
            $table->string('maturity')->default('13+');
            $table->json('colors')->nullable();

            $table->integer('seasons')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('titles');
    }
};
