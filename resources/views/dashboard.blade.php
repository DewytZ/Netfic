@extends('layouts.app')

@section('content')
    <h1>Panel</h1>
    <p>Hola, <strong>{{ auth()->user()->name }}</strong> ({{ auth()->user()->email }})</p>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Cerrar sesión</button>
    </form>
@endsection
