<?php

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
