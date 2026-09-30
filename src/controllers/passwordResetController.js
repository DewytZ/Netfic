import crypto from 'node:crypto';
import bcrypt from 'bcryptjs';
import config from '../config.js';
import { validatePassword } from '../passwordPolicy.js';
import { isValidEmail, normalizeEmail } from '../validation.js';

const hashToken = (token) => crypto.createHash('sha256').update(token).digest('hex');

export function passwordResetController(db, mailer) {
    const { expireMinutes, throttleSeconds } = config.passwordReset;

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
        });
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

        showReset(req, res) {
            res.render('auth/reset-password', {
                token: req.params.token,
                email: res.locals.old.email ?? req.query.email ?? '',
            });
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

            const { rows } = await db.query(
                'SELECT token, created_at FROM password_reset_tokens WHERE email = $1',
                [email],
            );
            const record = rows[0];
            const isExpired = record && Date.now() - new Date(record.created_at).getTime() > expireMinutes * 60 * 1000;
            const tokenMatches = record && crypto.timingSafeEqual(Buffer.from(record.token), Buffer.from(hashToken(token)));

            if (!record || isExpired || !tokenMatches) {
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

            res.flash({ status: 'Contraseña actualizada. Ya puedes iniciar sesión.' });
            res.redirect('/login');
        },
    };
}
