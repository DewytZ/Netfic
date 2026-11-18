<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Cuentas de prueba. Las contraseñas cumplen la política de la app (PasswordPolicy).
 */
class UserSeeder extends Seeder
{
    public const USERS = [
        [
            'name' => 'Administrador',
            'email' => 'admin@netfic.test',
            'password' => 'Admin123!',
            'role' => UserRole::Admin,
            'profiles' => [['name' => 'Admin', 'avatar' => 'purple']],
        ],
        [
            'name' => 'Usuario Demo',
            'email' => 'usuario@netfic.test',
            'password' => 'Usuario123!',
            'role' => UserRole::User,
            'profiles' => [
                ['name' => 'Ana', 'avatar' => 'blue'],
                ['name' => 'Luis', 'avatar' => 'green'],
                ['name' => 'Niños', 'avatar' => 'orange'],
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::USERS as $data) {
            $user = User::firstOrNew(['email' => $data['email']]);
            $user->forceFill([
                'name' => $data['name'],
                'password' => $data['password'],   // el cast "hashed" la encripta
                'role' => $data['role'],
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            foreach ($data['profiles'] as $profile) {
                $user->profiles()->updateOrCreate(
                    ['name' => $profile['name']],
                    ['avatar' => $profile['avatar']],
                );
            }
        }

        $this->command?->info('Usuarios: admin@netfic.test / Admin123!  ·  usuario@netfic.test / Usuario123!');
    }
}
