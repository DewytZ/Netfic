<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    private const AVATARS = [
        'purple',
        'blue',
        'green',
        'orange',
        'pink',
    ];

    public function index(Request $request): View
    {
        $profiles = $request->user()
            ->profiles()
            ->orderBy('id')
            ->get();

        return view('profiles.index', [
            'profiles' => $profiles,
        ]);
    }

    public function create(): View
    {
        return view('profiles.create', [
            'avatars' => self::AVATARS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'avatar' => ['required', Rule::in(self::AVATARS)],
        ], [
            'name.required' => 'Escribe un nombre para el perfil.',
            'name.max' => 'El nombre no puede superar los 50 caracteres.',
            'avatar.required' => 'Selecciona un avatar.',
            'avatar.in' => 'Selecciona un avatar válido.',
        ]);

        $request->user()->profiles()->create($validated);

        return redirect()
            ->route('profiles.index')
            ->with('success', 'Perfil creado correctamente.');
    }

    public function select(Request $request, string $profile): RedirectResponse
    {
        $selectedProfile = $request->user()
            ->profiles()
            ->whereKey($profile)
            ->firstOrFail();

        $request->session()->put(
            'active_profile_id',
            $selectedProfile->id
        );

        return redirect()->route('dashboard');
    }
}