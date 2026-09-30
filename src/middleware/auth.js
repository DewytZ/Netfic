/**
 * Carga el usuario de la sesión. Si su contraseña cambió desde que
 * inició sesión (session_version distinto), la sesión deja de valer.
 */
export function loadUser(db) {
    return async (req, res, next) => {
        req.user = null;

        if (req.session.userId) {
            const { rows } = await db.query(
                'SELECT id, name, email, session_version FROM users WHERE id = $1',
                [req.session.userId],
            );
            const user = rows[0];

            if (user && user.session_version === req.session.sessionVersion) {
                req.user = user;
            } else {
                delete req.session.userId;
                delete req.session.sessionVersion;
            }
        }

        res.locals.user = req.user;
        next();
    };
}

export function auth(req, res, next) {
    if (!req.user) {
        if (req.method === 'GET') {
            req.session.intended = req.originalUrl;
        }

        return res.redirect('/login');
    }

    next();
}

export function guest(req, res, next) {
    if (req.user) {
        return res.redirect('/dashboard');
    }

    next();
}
