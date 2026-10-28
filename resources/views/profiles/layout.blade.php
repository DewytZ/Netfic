<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Perfiles') | Netfic</title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            background:
                radial-gradient(circle at top, #27204b 0, transparent 55%),
                #0c0d16;
            color: #f5f5fa;
            font-family: Arial, sans-serif;
        }

        header {
            padding: 28px 5%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .brand {
            font-size: 32px;
            font-weight: 800;
        }

        .brand span { color: #8276ff; }

        main {
            width: min(900px, 92%);
            margin: 50px auto;
            text-align: center;
        }

        h1 {
            font-size: clamp(28px, 5vw, 44px);
            margin-bottom: 12px;
        }

        .muted { color: #a9abc2; }

        .grid {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 24px;
            margin-top: 38px;
        }

        .profile {
            width: 140px;
            background: none;
            border: none;
            color: inherit;
            font: inherit;
            cursor: pointer;
            text-decoration: none;
            display: block;
            padding: 0;
        }

        .avatar {
            width: 120px;
            height: 120px;
            margin: 0 auto 12px;
            border-radius: 22px;
            display: grid;
            place-items: center;
            font-size: 44px;
            font-weight: bold;
            border: 3px solid transparent;
            transition: transform .2s, border-color .2s;
        }

        .profile:hover .avatar,
        .profile:focus-visible .avatar {
            transform: translateY(-5px);
            border-color: white;
        }

        .purple { background: #6d5ce7; }
        .blue { background: #2563b5; }
        .green { background: #16734e; }
        .orange { background: #a94d18; }
        .pink { background: #aa3274; }

        .add {
            background: #202234;
            border: 2px dashed #79758f;
            color: #d8d3ff;
        }

        .profile-name {
            display: block;
            overflow-wrap: anywhere;
            line-height: 1.5;
        }

        .panel {
            max-width: 480px;
            margin: 30px auto;
            padding: 30px;
            background: #151624;
            border: 1px solid #333248;
            border-radius: 20px;
            text-align: left;
        }

        label, legend {
            display: block;
            margin-bottom: 10px;
        }

        input[type="text"] {
            width: 100%;
            padding: 14px;
            border-radius: 10px;
            border: 1px solid #55516d;
            background: #202132;
            color: white;
            font: inherit;
        }

        input:focus-visible, a:focus-visible, button:focus-visible {
            outline: 3px solid #ada4ff;
            outline-offset: 4px;
        }

        fieldset {
            border: 0;
            padding: 0;
            margin: 24px 0;
        }

        .avatar-options {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .avatar-option {
            cursor: pointer;
            text-align: center;
            font-size: 12px;
        }

        .swatch {
            display: block;
            width: 48px;
            height: 48px;
            border-radius: 12px;
            margin: 6px auto;
            border: 3px solid transparent;
        }

        .avatar-option input:checked + .swatch {
            border-color: white;
            box-shadow: 0 0 0 3px #8276ff;
        }

        .actions {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }

        .button {
            display: inline-block;
            padding: 12px 18px;
            border: 1px solid #77708f;
            border-radius: 10px;
            background: transparent;
            color: white;
            text-decoration: none;
            cursor: pointer;
            font: inherit;
        }

        .primary {
            background: #6654e8;
            border-color: #6654e8;
        }

        .error { color: #ffb0bc; font-size: 14px; }
        .notice { color: #c6f6d5; margin: 20px 0; }

        @media (max-width: 480px) {
            header { padding: 20px; }
            main { margin-top: 25px; }
            .panel { padding: 22px; }
            .grid { gap: 18px; }
            .profile { width: 120px; }
            .avatar { width: 100px; height: 100px; }
        }
    </style>
</head>

<body>
    <header>
        <div class="brand"><span>•</span> Netfic</div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="button" type="submit">Cerrar sesión</button>
        </form>
    </header>

    <main>
        @if (session('success'))
            <p class="notice" role="status">{{ session('success') }}</p>
        @endif

        @if (session('status'))
            <p class="notice" role="status">{{ session('status') }}</p>
        @endif

        @yield('content')
    </main>
</body>
</html>