import { promisify } from 'node:util';
import { newCsrfToken } from './middleware/csrf.js';

/**
 * Cambia el ID de sesión (evita session fixation) conservando solo lo indicado.
 */
export async function regenerateSession(req, keep = {}) {
    await promisify(req.session.regenerate).call(req.session);
    Object.assign(req.session, keep);
    newCsrfToken(req.session);
}

export async function loginUser(req, user, { remember = false, lifetimeMinutes, rememberDays }) {
    const intended = req.session.intended;

    await regenerateSession(req, {
        userId: user.id,
        sessionVersion: user.session_version,
    });

    req.session.cookie.maxAge = remember
        ? rememberDays * 24 * 60 * 60 * 1000
        : lifetimeMinutes * 60 * 1000;

    return intended ?? '/dashboard';
}
