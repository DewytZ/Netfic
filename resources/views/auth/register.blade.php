@extends('layouts.app')

@section('content')
    <h1>Registro</h1>
    <form method="POST" action="{{ route('register') }}">
        @csrf
        <label>Nombre <input type="text" name="name" value="{{ old('name') }}" required autofocus></label>
        <label>Email <input type="email" name="email" value="{{ old('email') }}" required></label>
        <label>Contraseña <input type="password" name="password" required></label>
        <label>Confirmar contraseña <input type="password" name="password_confirmation" required></label>
        <button type="submit">Crear cuenta</button>
    </form>
    <p>¿Ya tienes cuenta? <a href="{{ route('login') }}">Inicia sesión</a></p>
@endsection
