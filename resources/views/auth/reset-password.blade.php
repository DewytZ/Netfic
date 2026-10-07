@extends('layouts.auth')

@section('content')
<div class="auth-card" id="resetPasswordCard">
    @if ($invalidLink)
        <h1 class="auth-title">Enlace no válido</h1>
        <p class="auth-description">Este enlace para restablecer la contraseña ya expiró, ya se usó o está incompleto. Solicita uno nuevo; solo funciona el último que te enviamos.</p>

        <a href="{{ route('password.request') }}" class="btn-primary btn-link">Solicitar un nuevo enlace</a>
    @else
        <h1 class="auth-title">Nueva contraseña</h1>
        <p class="auth-description">Define tu nueva contraseña para recuperar el acceso a tu cuenta.</p>

        @include('partials.alerts')

        <form method="POST" action="{{ route('password.update') }}" id="resetPasswordForm">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <!-- Campo Email (viene del enlace) -->
            <div class="input-group">
                <input type="email" id="email" name="email" value="{{ $email }}" placeholder=" " required readonly autocomplete="username">
                <label for="email">Correo electrónico</label>
            </div>

            @include('auth.partials.password-fields')

            <button type="submit" class="btn-primary" id="btnSubmit">Guardar contraseña</button>
        </form>
    @endif

    <div class="auth-switch">
        <a href="{{ route('login') }}">← Volver a iniciar sesión</a>
    </div>
</div>
@endsection
