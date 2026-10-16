@extends('layouts.auth', ['title' => 'Acceso denegado - '.config('app.name')])

@section('content')
<div class="auth-card">
    <h1 class="auth-title">Acceso denegado</h1>
    <p class="auth-description">{{ $exception->getMessage() ?: 'No tienes permiso para ver esta página.' }}</p>

    <a href="{{ route('dashboard') }}" class="btn-primary btn-link">Volver al catálogo</a>
</div>
@endsection
