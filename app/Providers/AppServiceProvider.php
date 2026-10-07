<?php

namespace App\Providers;

use App\Auth\CachedUserProvider;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::provider('cached-eloquent', fn ($app, array $config) => new CachedUserProvider($app['hash'], $config['model']));
        Event::listen(Login::class, fn (Login $event) => CachedUserProvider::remember($event->user));

        // Máximo 5 intentos por minuto por IP en el login y en "olvidé mi contraseña".
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->ip())
                ->response(fn (Request $request, array $headers) => response(
                    'Demasiados intentos. Espera un minuto e intenta de nuevo.',
                    429,
                    $headers,
                ));
        });

        ResetPassword::toMailUsing(function (object $notifiable, string $token) {
            $data = [
                'name' => $notifiable->name,
                'url' => route('password.reset', [
                    'token' => $token,
                    'email' => $notifiable->getEmailForPasswordReset(),
                ]),
                'expireMinutes' => config('auth.passwords.users.expire'),
            ];

            return (new MailMessage)
                ->subject('Restablecer contraseña')
                ->view(['html' => 'mail.reset-password', 'text' => 'mail.reset-password-text'], $data);
        });
    }
}
