<?php

use App\Support\SupabaseSecurity;
use Illuminate\Database\Migrations\Migration;

/**
 * Cierra la API REST de Supabase sobre las tablas de la app: activa RLS en todas
 * y quita permisos a los roles "anon" y "authenticated". Laravel no se ve afectado.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        SupabaseSecurity::lockDownPublicSchema();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No se revierte: volver a abrir la API de Supabase expondría usuarios y contraseñas.
    }
};
