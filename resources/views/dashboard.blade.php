<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio - {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/styles.css') }}">
    @viteReactRefresh
    @vite('resources/js/browse/main.jsx')
</head>
<body class="browse-body">
    @if (session('status'))
        <div class="toast status-toast nf-status-toast" role="status"><p>{{ session('status') }}</p></div>
    @endif

    {{-- Catálogo tipo Netflix (componente React en resources/js/browse) --}}
    <div id="browse"
         data-user="{{ json_encode(['name' => auth()->user()->name, 'email' => auth()->user()->email]) }}"
         data-csrf="{{ csrf_token() }}"></div>

    <noscript>
        <div class="auth-wrapper">
            <p>Activa JavaScript para ver el catálogo, {{ auth()->user()->name }}.</p>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn-logout">Cerrar sesión</button>
            </form>
        </div>
    </noscript>
</body>
</html>
