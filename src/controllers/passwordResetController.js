import crypto from 'node:crypto';
import bcrypt from 'bcryptjs';
import config from '../config.js';
import { validatePassword } from '../passwordPolicy.js';
import { regenerateSession } from '../session.js';
import { isValidEmail, normalizeEmail } from '../validation.js';

const escapeHtml = (value) => String(value).replace(/[&<>"']/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
})[char]);

function resetEmailHtml({ name, url, expireMinutes, appName }) {
    const safeUrl = escapeHtml(url);

    return `<!DOCTYPE html>
<html lang="es">
<body style="margin:0;padding:32px 16px;background:#080b11;font-family:Arial,Helvetica,sans-serif;color:#f8fafc;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:480px;margin:0 auto;background:#0f1623;border:1px solid #1e2533;border-radius:16px;">
        <tr><td style="padding:36px 32px;">
            <p style="margin:0 0 24px;font-size:22px;font-weight:bold;">${escapeHtml(appName)}</p>
            <p style="margin:0 0 12px;font-size:16px;">Hola ${escapeHtml(name)},</p>
            <p style="margin:0 0 28px;font-size:15px;line-height:1.5;color:#94a3b8;">Recibimos una solicitud para restablecer la contraseña de tu cuenta. Haz clic en el botón para crear una nueva.</p>
            <a href="${safeUrl}" style="display:inline-block;padding:14px 28px;background:#6366f1;color:#ffffff;text-decoration:none;border-radius:10px;font-weight:bold;font-size:15px;">Restablecer contraseña</a>
            <p style="margin:28px 0 8px;font-size:13px;line-height:1.5;color:#94a3b8;">Este enlace expira en ${expireMinutes} minutos. Si no solicitaste el cambio, ignora este correo.</p>
            <p style="margin:0;font-size:12px;line-height:1.5;color:#64748b;word-break:break-all;">Si el botón no funciona, copia este enlace: ${safeUrl}</p>
        </td></tr>
    </table>
</body>
</html>`;
}

const hashToken = (token) => crypto.createHash('sha256').update(token).digest('hex');

export function passwordResetController(db, mailer) {
    const { expireMinutes, throttleSeconds } = config.passwordReset;

    async function isValidResetToken(email, token) {
        if (!token || !isValidEmail(email)) {
            return false;
        }

        const { rows } = await db.query(
            'SELECT token, created_at FROM password_reset_tokens WHERE email = $1',
            [email],
        );
        const record = rows[0];

        if (!record || Date.now() - new Date(record.created_at).getTime() > expireMinutes * 60 * 1000) {
            return false;
        }

        return crypto.timingSafeEqual(Buffer.from(record.token), Buffer.from(hashToken(token)));
    }

    async function sendResetEmail(email, name) {
        const { rows } = await db.query('SELECT created_at FROM password_reset_tokens WHERE email = $1', [email]);
        const lastRequest = rows[0]?.created_at;

        // Igual que Laravel: como máximo un correo por minuto para el mismo email.
        if (lastRequest && Date.now() - new Date(lastRequest).getTime() < throttleSeconds * 1000) {
            return;
        }

        const token = crypto.randomBytes(32).toString('hex');

        await db.query('DELETE FROM password_reset_tokens WHERE email = $1', [email]);
        await db.query(
            'INSERT INTO password_reset_tokens (email, token, created_at) VALUES ($1, $2, $3)',
            [email, hashToken(token), new Date()],
        );

        const url = `${config.app.url}/reset-password/${token}?email=${encodeURIComponent(email)}`;

        try {
            await mailer.send({
                to: email,
                subject: 'Restablecer contraseña',
                text: [
                    `Hola ${name}`,
                    '',
                    'Recibimos una solicitud para restablecer la contraseña de tu cuenta.',
                    `Abre este enlace para crear una nueva: ${url}`,
                    '',
                    `Este enlace expira en ${expireMinutes} minutos.`,
                    'Si no solicitaste el cambio, ignora este correo.',
                    '',
                    `Saludos, ${config.app.name}`,
                ].join('\n'),
                html: resetEmailHtml({ name, url, expireMinutes, appName: config.app.name }),
            });
        } catch (error) {
            // Sin correo enviado el token no sirve; se borra para poder reintentar sin esperar el límite.
            await db.query('DELETE FROM password_reset_tokens WHERE email = $1', [email]);
            console.error(`No se pudo enviar el correo de restablecimiento a ${email}:`, error.message);
        }
    }

    return {
        showForgot(req, res) {
            res.render('auth/forgot-password');
        },

        async sendResetLink(req, res) {
            const email = normalizeEmail(req.body.email);

            if (!isValidEmail(email)) {
                res.flash({ errors: ['El email no es válido.'], old: { email } });
                return res.redirect('/forgot-password');
            }

            const { rows } = await db.query('SELECT name FROM users WHERE email = $1', [email]);

            if (rows[0]) {
                await sendResetEmail(email, rows[0].name);
            }

            // Mismo mensaje exista o no el correo, para no revelar qué cuentas están registradas.
            res.flash({ status: 'Si el correo está registrado, te enviamos un enlace para restablecer la contraseña.' });
            res.redirect('/forgot-password');
        },

        async showReset(req, res) {
            const token = req.params.token;
            const email = res.locals.old.email ?? normalizeEmail(req.query.email ?? '');

            // Se revisa al abrir el enlace para no hacer llenar el formulario a quien trae uno vencido o ya usado.
            const invalidLink = !(await isValidResetToken(email, token));

            res.render('auth/reset-password', { token, email, invalidLink });
        },

        async reset(req, res) {
            const token = String(req.body.token ?? '');
            const email = normalizeEmail(req.body.email);
            const { password, password_confirmation: confirmation } = req.body;
            const backToForm = `/reset-password/${encodeURIComponent(token)}`;

            const errors = [];
            if (!token) {
                errors.push('Falta el token de restablecimiento.');
            }
            if (!isValidEmail(email)) {
                errors.push('El email no es válido.');
            }
            errors.push(...validatePassword(password, confirmation));

            if (errors.length) {
                res.flash({ errors, old: { email } });
                return res.redirect(backToForm);
            }

            if (!(await isValidResetToken(email, token))) {
                res.flash({ errors: ['El enlace no es válido o ya expiró. Solicita uno nuevo.'], old: { email } });
                return res.redirect(backToForm);
            }

            const hash = await bcrypt.hash(password, 12);

            // Subir session_version cierra las sesiones abiertas con la contraseña anterior.
            await db.query(
                'UPDATE users SET password = $1, session_version = session_version + 1, updated_at = $2 WHERE email = $3',
                [hash, new Date(), email],
            );
            await db.query('DELETE FROM password_reset_tokens WHERE email = $1', [email]);

            // Si el enlace se abrió con otra sesión iniciada, se cierra para entrar con la cuenta recién cambiada.
            await regenerateSession(req);
            res.flash({ status: 'Contraseña actualizada. Ya puedes iniciar sesión.' });
            res.redirect('/login');
        },
    };
}
