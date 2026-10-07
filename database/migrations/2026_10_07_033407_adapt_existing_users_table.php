<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adapta la tabla users creada por la versión en Node para que funcione con Laravel.
 * En una base nueva no hace nada, porque create_users_table ya crea todo.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable();
            }

            if (! Schema::hasColumn('users', 'remember_token')) {
                $table->rememberToken();
            }
        });

        // bcryptjs guarda los hash con el prefijo $2b$ (o $2a$) y Laravel solo acepta $2y$.
        // Es el mismo algoritmo, así que basta con cambiar el prefijo: las contraseñas siguen siendo las mismas.
        DB::table('users')
            ->where('password', 'like', '$2b$%')
            ->orWhere('password', 'like', '$2a$%')
            ->update(['password' => DB::raw("'\$2y\$' || substr(password, 5)")]);

        // Los tokens de restablecimiento de Node usan otro formato y ya no sirven.
        DB::table('password_reset_tokens')->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No se revierte: los hash $2y$ también funcionan en Node y las columnas nuevas son opcionales.
    }
};
