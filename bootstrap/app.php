<?php

if (!function_exists('mb_strimwidth')) {
    function mb_strimwidth(string $string, int $start, int $width, string $trimmarker = '', ?string $encoding = null): string {
        $encoding = $encoding ?? 'UTF-8';
        if (mb_strwidth($string, $encoding) <= $width) {
            return $string;
        }
        $trimWidth = mb_strwidth($trimmarker, $encoding);
        return mb_substr($string, $start, max(0, $width - $trimWidth), $encoding) . $trimmarker;
    }
}

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Cierra la sesión si la contraseña cambió desde que se inició (por ejemplo, tras restablecerla en otro dispositivo).
        $middleware->web(append: [AuthenticateSession::class]);

        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/dashboard');
        // La API vuelve a exigir el token CSRF: el panel de admin lo envía en la cabecera X-CSRF-TOKEN.
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
