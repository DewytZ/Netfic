@extends('layouts.auth', ['movieBackground' => true])

@section('content')
<div class="auth-card" id="forgotCard">
    <h1 class="auth-title">¿Olvidaste tu contraseña?</h1>
    <p class="auth-description">Escribe tu correo electrónico y te enviaremos un enlace para crear una nueva contraseña.</p>

    @include('partials.alerts')

    <form method="POST" action="{{ route('password.email') }}" id="forgotForm">
        @csrf

        <div class="input-group">
            <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder=" " required autofocus>
            <label for="email">Correo electrónico</label>
        </div>

        <button type="submit" class="btn-primary" id="btnSubmit">Enviar enlace</button>
    </form>

    <div class="auth-switch">
        <a href="{{ route('login') }}">← Volver a iniciar sesión</a>
    </div>
</div>
@endsection
