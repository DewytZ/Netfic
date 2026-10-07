<!DOCTYPE html>
<html lang="es">
<body style="margin:0;padding:32px 16px;background:#080b11;font-family:Arial,Helvetica,sans-serif;color:#f8fafc;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:480px;margin:0 auto;background:#0f1623;border:1px solid #1e2533;border-radius:16px;">
        <tr><td style="padding:36px 32px;">
            <p style="margin:0 0 24px;font-size:22px;font-weight:bold;">{{ config('app.name') }}</p>
            <p style="margin:0 0 12px;font-size:16px;">Hola {{ $name }},</p>
            <p style="margin:0 0 28px;font-size:15px;line-height:1.5;color:#94a3b8;">Recibimos una solicitud para restablecer la contraseña de tu cuenta. Haz clic en el botón para crear una nueva.</p>
            <a href="{{ $url }}" style="display:inline-block;padding:14px 28px;background:#6366f1;color:#ffffff;text-decoration:none;border-radius:10px;font-weight:bold;font-size:15px;">Restablecer contraseña</a>
            <p style="margin:28px 0 8px;font-size:13px;line-height:1.5;color:#94a3b8;">Este enlace expira en {{ $expireMinutes }} minutos. Si no solicitaste el cambio, ignora este correo.</p>
            <p style="margin:0;font-size:12px;line-height:1.5;color:#64748b;word-break:break-all;">Si el botón no funciona, copia este enlace: {{ $url }}</p>
        </td></tr>
    </table>
</body>
</html>
