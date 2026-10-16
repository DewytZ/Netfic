<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Solo deja pasar a usuarios con rol de administrador.
 * Va después de "auth", así que aquí siempre hay un usuario con sesión.
 */
class EnsureUserIsAdmin
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isAdmin()) {
            abort(403, 'Solo los administradores pueden entrar a esta sección.');
        }

        return $next($request);
    }
}
