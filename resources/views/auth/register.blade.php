@extends('layouts.auth', ['movieBackground' => true])

@section('content')
<div class="auth-card" id="registerCard">
    <h1 class="auth-title">Crear cuenta</h1>
    <p class="auth-description">Regístrate para comenzar a disfrutar de Netfic.</p>

    @include('partials.alerts')

    <form method="POST" action="{{ route('register') }}" id="registerForm">
        @csrf

        <!-- Campo Nombre de usuario -->
        <div class="input-group">
            <input type="text" id="name" name="name" value="{{ old('name') }}" placeholder=" " required autocomplete="username" autofocus>
            <label for="name">Nombre de usuario</label>
        </div>

        <!-- Campo Email -->
        <div class="input-group">
            <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder=" " required>
            <label for="email">Correo electrónico</label>
        </div>

        @include('auth.partials.password-fields')

        <button type="submit" class="btn-primary" id="btnRegister">Crear cuenta</button>
    </form>

    <div class="auth-switch">
        ¿Ya tienes cuenta? <a href="{{ route('login') }}">Inicia sesión</a>.
    </div>
</div>
@endsection
