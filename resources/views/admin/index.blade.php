<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - Netfic</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    {{-- Vite: Magia de actualización en tiempo real --}}
    @viteReactRefresh
    @vite('resources/js/admin/main.jsx')
</head>
<body style="background-color: #141414; margin: 0; min-height: 100vh;">
    {{-- Aquí es donde React va a inyectar tu TitlesTable --}}
    <div id="admin-root"></div>
</body>
</html>