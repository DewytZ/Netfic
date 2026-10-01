import path from 'node:path';
import connectPgSimple from 'connect-pg-simple';
import express from 'express';
import session from 'express-session';
import config from './config.js';
import { loadUser } from './middleware/auth.js';
import { csrf } from './middleware/csrf.js';
import { flash } from './middleware/flash.js';
import { createRouter } from './routes.js';

/**
 * sessionStore es opcional: si no se pasa, las sesiones se guardan en la tabla "session" de Postgres.
 */
export function createApp({ db, mailer, sessionStore, sessionSecret = config.session.secret }) {
    const app = express();
    const PgSession = connectPgSimple(session);

    app.set('view engine', 'ejs');
    app.set('views', path.resolve(import.meta.dirname, '../views'));
    app.use(express.static(path.resolve(import.meta.dirname, '../public')));
    app.set('trust proxy', 1);
    app.locals.appName = config.app.name;

    app.use(express.urlencoded({ extended: false }));
    app.use(
        session({
            store: sessionStore ?? new PgSession({ pool: db, tableName: 'session' }),
            secret: sessionSecret,
            name: 'login_app_session',
            resave: false,
            saveUninitialized: false,
            rolling: true,
            cookie: {
                httpOnly: true,
                sameSite: 'lax',
                secure: config.app.env === 'production',
                maxAge: config.session.lifetimeMinutes * 60 * 1000,
            },
        }),
    );
    app.use(csrf);
    app.use(flash);
    app.use(loadUser(db));
    app.use(createRouter({ db, mailer }));

    app.use((req, res) => res.status(404).send('Página no encontrada.'));

    return app;
}
