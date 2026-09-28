<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <style>
        body { font-family: sans-serif; max-width: 400px; margin: 60px auto; padding: 0 16px; }
        label { display: block; margin-top: 12px; }
        input[type=text], input[type=email], input[type=password] { width: 100%; padding: 8px; box-sizing: border-box; }
        button { margin-top: 16px; padding: 8px 16px; cursor: pointer; }
        .error { color: #b00020; }
        .status { color: #1b5e20; }
    </style>
</head>
<body>
    @if (session('status'))
        <p class="status">{{ session('status') }}</p>
    @endif

    @if ($errors->any())
        <ul class="error">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    @yield('content')
</body>
</html>
