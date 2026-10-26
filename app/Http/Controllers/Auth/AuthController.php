<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Content;
use App\Models\Title;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Throwable;

class AuthController extends Controller
{
    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(RegisterRequest $request): RedirectResponse
    {
        $user = User::create($request->validated());

        Auth::login($user);

        $request->session()->regenerate();
        $request->session()->forget('active_profile_id');
        $request->session()->forget('url.intended');

        return redirect()
            ->route('profiles.index')
            ->with('status', 'Cuenta creada correctamente.');
    }

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        if (! Auth::attempt(
            $request->only('email', 'password'),
            $request->boolean('remember')
        )) {
            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'Las credenciales no son correctas.',
                ])
                ->onlyInput('email');
        }

        $request->session()->regenerate();
        $request->session()->forget('active_profile_id');

        // Conservamos el comportamiento actual para administradores.
        if ($request->user()->isAdmin()) {
            return redirect()->intended(route('admin.index'));
        }

        // Los usuarios seleccionan un perfil antes del catálogo.
        $request->session()->forget('url.intended');

        return redirect()->route('profiles.index');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('status', 'Sesión cerrada.');
    }

    public function dashboard(Request $request): View|RedirectResponse
    {
        // Los administradores conservan el acceso directo al catálogo.
        if (! $request->user()->isAdmin()) {
            $activeProfileId = $request->session()->get('active_profile_id');

            $hasValidProfile = $activeProfileId !== null
                && $request->user()
                    ->profiles()
                    ->whereKey($activeProfileId)
                    ->exists();

            if (! $hasValidProfile) {
                $request->session()->forget('active_profile_id');

                return redirect()->route('profiles.index');
            }
        }

        // Todo el catálogo sale de la base de datos (tabla titles).
        $titles = Title::orderBy('id')
            ->get([
                'id',
                'title',
                'type',
                'genre',
                'year',
                'duration',
                'seasons',
                'maturity',
                'description',
                'colors',
            ]);

        return view('dashboard', [
            'titles' => $titles,
            'contents' => $this->multimediaContents(),
        ]);
    }

    // Contenido libre de copyright de MongoDB (Internet Archive, frutinovelas, IA).
    // Si MongoDB no responde, el dashboard sigue funcionando solo con la tabla titles.
    private function multimediaContents(): Collection
    {
        try {
            return Content::published()
                ->orderBy('created_at', 'desc')
                ->limit(200)
                ->get([
                    'title',
                    'slug',
                    'type',
                    'source',
                    'license',
                    'synopsis',
                    'genres',
                    'year',
                    'duration',
                    'maturity',
                    'poster_url',
                    'video_url',
                    'embed_url',
                    'episodes',
                    'tags',
                    'source_ref',
                ]);
        } catch (Throwable $e) {
            report($e);

            return collect();
        }
    }
}