@extends('layouts.app')

@section('content')
    <h1>Iniciar sesión</h1>
    <form method="POST" action="{{ route('login') }}">
        @csrf
        <label>Email <input type="email" name="email" value="{{ old('email') }}" required autofocus></label>
        <label>Contraseña <input type="password" name="password" required></label>
        <label><input type="checkbox" name="remember"> Recordarme</label>
        <button type="submit">Entrar</button>
    </form>
    <p>¿No tienes cuenta? <a href="{{ route('register') }}">Regístrate</a></p>
@endsection
