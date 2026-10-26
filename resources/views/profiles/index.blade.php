@extends('profiles.layout')

@section('title', 'Seleccionar perfil')

@section('content')
    <h1>¿Quién está viendo?</h1>
    <p class="muted">Selecciona tu perfil para continuar.</p>

    @if ($profiles->isEmpty())
        <p class="muted">Todavía no tienes perfiles. Crea el primero.</p>
    @endif

    <div class="grid">
        @foreach ($profiles as $profile)
            @php
                $avatarClass = in_array(
                    $profile->avatar,
                    ['purple', 'blue', 'green', 'orange', 'pink'],
                    true
                ) ? $profile->avatar : 'purple';
            @endphp

            <form method="POST"
                  action="{{ route('profiles.select', ['profile' => $profile->id]) }}">
                @csrf

                <button type="submit" class="profile">
                    <span class="avatar {{ $avatarClass }}" aria-hidden="true">
                        {{ mb_strtoupper(mb_substr($profile->name, 0, 1)) }}
                    </span>

                    <span class="profile-name">{{ $profile->name }}</span>
                </button>
            </form>
        @endforeach

        <a href="{{ route('profiles.create') }}" class="profile">
            <span class="avatar add" aria-hidden="true">+</span>
            <span class="profile-name">Agregar perfil</span>
        </a>
    </div>
@endsection