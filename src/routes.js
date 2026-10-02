import { Router } from 'express';
import { rateLimit } from 'express-rate-limit';
import { authController } from './controllers/authController.js';
import { passwordResetController } from './controllers/passwordResetController.js';
import { auth, guest } from './middleware/auth.js';

/**
 * Máximo 5 intentos por minuto por IP (como throttle:5,1 en Laravel).
 */
const throttle = () =>
    rateLimit({
        windowMs: 60 * 1000,
        limit: 5,
        standardHeaders: 'draft-8',
        legacyHeaders: false,
        message: 'Demasiados intentos. Espera un minuto e intenta de nuevo.',
    });

export function createRouter({ db, mailer }) {
    const router = Router();
    const authCtrl = authController(db);
    const resetCtrl = passwordResetController(db, mailer);

    router.get('/', (req, res) => res.redirect('/dashboard'));

    router.get('/register', guest, authCtrl.showRegister);
    router.post('/register', guest, authCtrl.register);
    router.get('/login', guest, authCtrl.showLogin);
    router.post('/login', guest, throttle(), authCtrl.login);

    router.get('/forgot-password', guest, resetCtrl.showForgot);
    router.post('/forgot-password', guest, throttle(), resetCtrl.sendResetLink);
    // Sin "guest": el enlace del correo debe abrir aunque haya una sesión iniciada en el navegador.
    router.get('/reset-password/:token', resetCtrl.showReset);
    router.post('/reset-password', resetCtrl.reset);

    router.get('/dashboard', auth, authCtrl.dashboard);
    router.post('/logout', auth, authCtrl.logout);

    return router;
}
