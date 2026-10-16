<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Todos los usuarios existentes y nuevos empiezan como "user"; los admins se asignan con "php artisan user:role".
            $table->string('role', 20)->default('user')->index();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("alter table users add constraint users_role_check check (role in ('user', 'admin'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('alter table users drop constraint if exists users_role_check');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });
    }
};
