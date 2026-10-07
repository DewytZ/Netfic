<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/styles.css') }}">
</head>
<body class="auth-body">
    @if ($movieBackground ?? false)
        @include('partials.movie-background')
    @endif
    <header class="main-header">
        <div class="logo-container">
            <span class="logo-text">
                <span class="logo-dot"></span>
                Netfic
            </span>
        </div>
    </header>

    <main class="auth-wrapper">
        @yield('content')
    </main>

    <!-- Motor GSAP -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <!-- Animaciones UI -->
    <script src="{{ asset('js/auth-animations.js') }}"></script>
    <!-- Botón ver/ocultar contraseña -->
    <script src="{{ asset('js/password-toggle.js') }}"></script>
</body>
</html>
