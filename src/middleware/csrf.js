import crypto from 'node:crypto';

export function newCsrfToken(session) {
    session.csrfToken = crypto.randomBytes(32).toString('hex');
}

/**
 * Equivalente a @csrf de Laravel: cada formulario POST debe enviar _token.
 */
export function csrf(req, res, next) {
    if (!req.session.csrfToken) {
        newCsrfToken(req.session);
    }

    res.locals.csrfToken = req.session.csrfToken;

    if (req.method !== 'POST') {
        return next();
    }

    const sent = Buffer.from(String(req.body?._token ?? ''));
    const expected = Buffer.from(req.session.csrfToken);

    if (sent.length !== expected.length || !crypto.timingSafeEqual(sent, expected)) {
        return res.status(419).send('La página expiró. Vuelve atrás, recarga e intenta de nuevo.');
    }

    next();
}
