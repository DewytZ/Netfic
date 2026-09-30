import fs from 'node:fs';
import path from 'node:path';
import nodemailer from 'nodemailer';
import config from './config.js';

/**
 * MAIL_MAILER=log escribe el correo en logs/mail.log en vez de enviarlo.
 * MAIL_MAILER=smtp lo envía por el servidor SMTP configurado en .env.
 */
export function createMailer(mailConfig = config.mail) {
    const from = { name: mailConfig.fromName, address: mailConfig.fromAddress };

    if (mailConfig.mailer === 'smtp') {
        const transport = nodemailer.createTransport({
            host: mailConfig.host,
            port: mailConfig.port,
            secure: mailConfig.port === 465,
            auth: mailConfig.user ? { user: mailConfig.user, pass: mailConfig.password } : undefined,
        });

        return { send: (message) => transport.sendMail({ from, ...message }) };
    }

    const transport = nodemailer.createTransport({ streamTransport: true, buffer: true, newline: 'unix' });
    const logFile = path.resolve('logs/mail.log');

    return {
        async send(message) {
            const info = await transport.sendMail({ from, ...message });
            fs.mkdirSync(path.dirname(logFile), { recursive: true });
            fs.appendFileSync(logFile, `[${new Date().toISOString()}]\n${info.message.toString()}\n\n`);
            console.log(`Correo para ${message.to} guardado en logs/mail.log`);
        },
    };
}
