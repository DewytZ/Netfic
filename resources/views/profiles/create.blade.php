@extends('profiles.layout')

@section('title', 'Crear perfil')

@section('content')
    <h1>Agregar perfil</h1>
    <p class="muted">Elige un nombre y un color para identificarlo.</p>

    @php
        $colorNames = [
            'purple' => 'Morado',
            'blue' => 'Azul',
            'green' => 'Verde',
            'orange' => 'Naranja',
            'pink' => 'Rosa',
        ];
    @endphp

    <form method="POST"
          action="{{ route('profiles.store') }}"
          class="panel">
        @csrf

        <label for="name">Nombre del perfil</label>

        <input
            type="text"
            id="name"
            name="name"
            value="{{ old('name') }}"
            maxlength="50"
            required
            autofocus
            placeholder="Por ejemplo: Brad"
            @error('name')
                aria-invalid="true"
                aria-describedby="name-error"
            @enderror
        >

        @error('name')
            <p class="error" id="name-error">{{ $message }}</p>
        @enderror

        <fieldset>
            <legend>Color del avatar</legend>

            <div class="avatar-options">
                @foreach ($avatars as $avatar)
                    <label class="avatar-option">
                        <input
                            type="radio"
                            name="avatar"
                            value="{{ $avatar }}"
                            @checked(old('avatar', 'purple') === $avatar)
                            required
                        >

                        <span class="swatch {{ $avatar }}" aria-hidden="true"></span>
                        <span>{{ $colorNames[$avatar] ?? $avatar }}</span>
                    </label>
                @endforeach
            </div>

            @error('avatar')
                <p class="error">{{ $message }}</p>
            @enderror
        </fieldset>

        <div class="actions">
            <button type="submit" class="button primary">
                Guardar perfil
            </button>

            <a href="{{ route('profiles.index') }}" class="button">
                Cancelar
            </a>
        </div>
    </form>
@endsection