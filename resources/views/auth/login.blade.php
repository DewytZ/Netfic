@extends('layouts.auth', ['movieBackground' => true])

@section('content')
<div class="auth-card" id="loginCard">
    <h1 class="auth-title">Iniciar sesión</h1>
    <p class="auth-description">Ingresa tus credenciales para acceder a la plataforma.</p>

    @include('partials.alerts')

    <form method="POST" action="{{ route('login') }}" id="loginForm">
        @csrf

        <div class="input-group{{ $errors->any() ? ' has-error' : '' }}">
            <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder=" " required autocomplete="email" @if (! $errors->any()) autofocus @endif>
            <label for="email">Correo electrónico</label>
        </div>

        <div class="input-group{{ $errors->any() ? ' has-error' : '' }}">
            <input type="password" id="password" name="password" placeholder=" " required autocomplete="current-password" @if ($errors->any()) autofocus @endif>
            <label for="password">Contraseña</label>
        </div>

        <div class="auth-options">
            <label class="remember-me">
                <input type="checkbox" name="remember">
                Recordarme
            </label>
            <a href="{{ route('password.request') }}" class="auth-link">¿Olvidaste tu contraseña?</a>
        </div>

        <button type="submit" class="btn-primary" id="btnSubmit">Continuar</button>
    </form>

    <div class="auth-switch">
        ¿No tienes cuenta? <a href="{{ route('register') }}">Regístrate aquí</a>.
    </div>
</div>
@endsection
