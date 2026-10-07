Hola {{ $name }}

Recibimos una solicitud para restablecer la contraseña de tu cuenta.
Abre este enlace para crear una nueva: {!! $url !!}

Este enlace expira en {{ $expireMinutes }} minutos.
Si no solicitaste el cambio, ignora este correo.

Saludos, {{ config('app.name') }}
