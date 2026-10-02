import config from '../src/config.js';
import { createMailer } from '../src/mail.js';

const to = process.argv[2];

if (!to) {
    console.error('Uso: npm run mail:test -- tu-correo@ejemplo.com');
    process.exit(1);
}

try {
    await createMailer().send({
        to,
        subject: `Prueba de correo - ${config.app.name}`,
        text: `Si lees esto, el envío de correos de ${config.app.name} funciona (MAIL_MAILER=${config.mail.mailer}).`,
    });
    console.log(`Correo de prueba enviado a ${to} con MAIL_MAILER=${config.mail.mailer}`);
} catch (error) {
    console.error('No se pudo enviar el correo:', error.message);
    process.exitCode = 1;
}
