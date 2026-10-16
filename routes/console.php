<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('mail:test {email}', function (string $email) {
    $mailer = config('mail.default');

    try {
        Mail::raw(
            'Si lees esto, el envío de correos de '.config('app.name')." funciona (MAIL_MAILER={$mailer}).",
            fn (Message $message) => $message->to($email)->subject('Prueba de correo - '.config('app.name')),
        );
    } catch (Throwable $exception) {
        $this->error('No se pudo enviar el correo: '.$exception->getMessage());

        return 1;
    }

    $this->info("Correo de prueba enviado a {$email} con MAIL_MAILER={$mailer}");
})->purpose('Envía un correo de prueba para revisar la configuración de MAIL_*');

Artisan::command('user:role {email : Correo del usuario} {role=admin : user o admin}', function (string $email, string $role) {
    $newRole = UserRole::tryFrom($role);

    if (! $newRole) {
        $this->error('Rol no válido. Usa: '.implode(' o ', array_column(UserRole::cases(), 'value')));

        return 1;
    }

    $user = User::where('email', strtolower(trim($email)))->first();

    if (! $user) {
        $this->error("No existe ningún usuario con el correo {$email}.");

        return 1;
    }

    $user->role = $newRole;
    $user->save();

    $this->info("{$user->email} ahora es {$newRole->label()} ({$newRole->value}).");
})->purpose('Cambia el rol de un usuario (user o admin)');
