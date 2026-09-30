import bcrypt from 'bcryptjs';
import config from '../config.js';
import { validatePassword } from '../passwordPolicy.js';
import { loginUser, regenerateSession } from '../session.js';
import { isValidEmail, normalizeEmail } from '../validation.js';

const BCRYPT_ROUNDS = 12;
// Hash de relleno para que el login tarde lo mismo aunque el email no exista.
const DUMMY_HASH = bcrypt.hashSync('dummy-password', BCRYPT_ROUNDS);

export function authController(db) {
    return {
        showRegister(req, res) {
            res.render('auth/register');
        },

        async register(req, res) {
            const name = String(req.body.name ?? '').trim();
            const email = normalizeEmail(req.body.email);
            const { password, password_confirmation: confirmation } = req.body;
            const errors = [];

            if (!name) {
                errors.push('El nombre es obligatorio.');
            } else if (name.length > 255) {
                errors.push('El nombre no puede tener más de 255 caracteres.');
            }

            if (!isValidEmail(email)) {
                errors.push('El email no es válido.');
            } else {
                const { rows } = await db.query('SELECT 1 FROM users WHERE email = $1', [email]);
                if (rows.length) {
                    errors.push('Ese email ya está registrado.');
                }
            }

            errors.push(...validatePassword(password, confirmation));

            if (errors.length) {
                res.flash({ errors, old: { name, email } });
                return res.redirect('/register');
            }

            const hash = await bcrypt.hash(password, BCRYPT_ROUNDS);
            const { rows } = await db.query(
                'INSERT INTO users (name, email, password) VALUES ($1, $2, $3) RETURNING id, session_version',
                [name, email, hash],
            );

            await loginUser(req, rows[0], config.session);
            res.flash({ status: 'Cuenta creada correctamente.' });
            res.redirect('/dashboard');
        },

        showLogin(req, res) {
            res.render('auth/login');
        },

        async login(req, res) {
            const email = normalizeEmail(req.body.email);
            const password = String(req.body.password ?? '');

            if (!isValidEmail(email) || !password) {
                res.flash({ errors: ['Escribe un email válido y tu contraseña.'], old: { email } });
                return res.redirect('/login');
            }

            const { rows } = await db.query(
                'SELECT id, password, session_version FROM users WHERE email = $1',
                [email],
            );
            const user = rows[0];
            const passwordMatches = await bcrypt.compare(password, user?.password ?? DUMMY_HASH);

            if (!user || !passwordMatches) {
                res.flash({ errors: ['Las credenciales no son correctas.'], old: { email } });
                return res.redirect('/login');
            }

            const redirectTo = await loginUser(req, user, {
                ...config.session,
                remember: req.body.remember === 'on',
            });

            res.redirect(redirectTo);
        },

        async logout(req, res) {
            await regenerateSession(req);
            res.flash({ status: 'Sesión cerrada.' });
            res.redirect('/login');
        },

        dashboard(req, res) {
            res.render('dashboard');
        },
    };
}
