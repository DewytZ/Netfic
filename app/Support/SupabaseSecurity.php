<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Supabase publica las tablas del esquema "public" en su API REST para los roles
 * "anon" y "authenticated". Esta app solo accede a la base desde Laravel (rol postgres,
 * que se salta RLS), así que esas vías se cierran por completo.
 */
class SupabaseSecurity
{
    private const API_ROLES = ['anon', 'authenticated'];

    /**
     * Activa RLS en todas las tablas de "public" y quita los permisos de los roles de la API.
     * Es idempotente y no hace nada fuera de PostgreSQL o en un Postgres sin los roles de Supabase.
     */
    public static function lockDownPublicSchema(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $tables = DB::table('pg_tables')->where('schemaname', 'public')->pluck('tablename');

        foreach ($tables as $table) {
            DB::statement(sprintf('alter table public.%s enable row level security', self::quote($table)));
        }

        $apiRoles = self::existingApiRoles();

        if ($apiRoles === []) {
            return;
        }

        $roles = implode(', ', array_map(self::quote(...), $apiRoles));

        DB::statement("revoke all on all tables in schema public from {$roles}");
        DB::statement("revoke all on all sequences in schema public from {$roles}");
        // También para las tablas que se creen después (por ejemplo, en nuevas migraciones).
        DB::statement("alter default privileges in schema public revoke all on tables from {$roles}");
        DB::statement("alter default privileges in schema public revoke all on sequences from {$roles}");
    }

    /**
     * @return list<string>
     */
    public static function existingApiRoles(): array
    {
        return DB::table('pg_roles')->whereIn('rolname', self::API_ROLES)->pluck('rolname')->all();
    }

    private static function quote(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
}
