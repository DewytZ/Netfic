<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class PasswordResetController extends Controller
{
    public function showForgot(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(ForgotPasswordRequest $request): RedirectResponse
    {
        $email = $request->validated('email');

        try {
            // El broker ya limita a un correo por minuto para el mismo email (auth.passwords.users.throttle).
            Password::sendResetLink(['email' => $email]);
        } catch (Throwable $exception) {
            // Sin correo enviado el token no sirve; se borra para poder reintentar sin esperar el límite.
            if ($user = User::where('email', $email)->first()) {
                Password::deleteToken($user);
            }

            report($exception);
        }

        // Mismo mensaje exista o no el correo, para no revelar qué cuentas están registradas.
        return redirect()->route('password.request')
            ->with('status', 'Si el correo está registrado, te enviamos un enlace para restablecer la contraseña.');
    }

    public function showReset(Request $request, string $token): View
    {
        $email = Str::lower((string) old('email', $request->query('email', '')));
        $user = $email !== '' ? User::where('email', $email)->first() : null;

        // Se revisa al abrir el enlace para no hacer llenar el formulario a quien trae uno vencido o ya usado.
        $isInvalidLink = ! $user || ! Password::tokenExists($user, $token);

        return view('auth.reset-password', [
            'token' => $token,
            'email' => $email,
            'invalidLink' => $isInvalidLink,
        ]);
    }

    public function reset(ResetPasswordRequest $request): RedirectResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                // Cambiar la contraseña cierra las demás sesiones (middleware AuthenticateSession)
                // y el nuevo remember_token invalida las cookies de "Recordarme".
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return redirect()->route('password.reset', ['token' => $request->input('token')])
                ->withErrors(['email' => 'El enlace no es válido o ya expiró. Solicita uno nuevo.'])
                ->onlyInput('email');
        }

        // Si el enlace se abrió con otra sesión iniciada, se cierra para entrar con la cuenta recién cambiada.
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Contraseña actualizada. Ya puedes iniciar sesión.');
    }
}
