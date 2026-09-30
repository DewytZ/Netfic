try {
    process.loadEnvFile();
} catch {
    // Sin archivo .env: se usan las variables de entorno del sistema.
}

const env = process.env;

export default {
    app: {
        name: env.APP_NAME ?? 'Login App',
        env: env.APP_ENV ?? 'development',
        url: env.APP_URL ?? 'http://localhost:8000',
        port: Number(env.APP_PORT ?? 8000),
    },
    session: {
        secret: env.SESSION_SECRET,
        lifetimeMinutes: Number(env.SESSION_LIFETIME ?? 120),
        rememberDays: 30,
    },
    db: {
        host: env.DB_HOST ?? '127.0.0.1',
        port: Number(env.DB_PORT ?? 5432),
        database: env.DB_DATABASE,
        user: env.DB_USERNAME,
        password: env.DB_PASSWORD,
    },
    mail: {
        mailer: env.MAIL_MAILER ?? 'log',
        host: env.MAIL_HOST,
        port: Number(env.MAIL_PORT ?? 587),
        user: env.MAIL_USERNAME,
        password: env.MAIL_PASSWORD,
        fromAddress: env.MAIL_FROM_ADDRESS ?? 'hello@example.com',
        fromName: env.MAIL_FROM_NAME ?? env.APP_NAME ?? 'Login App',
    },
    passwordReset: {
        expireMinutes: 60,
        throttleSeconds: 60,
    },
};
