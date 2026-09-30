import config from './config.js';
import { createApp } from './app.js';
import { createPool } from './db.js';
import { createMailer } from './mail.js';

if (!config.session.secret) {
    console.error('Falta SESSION_SECRET en el archivo .env');
    process.exit(1);
}

const app = createApp({ db: createPool(), mailer: createMailer() });

app.listen(config.app.port, () => {
    console.log(`${config.app.name} corriendo en ${config.app.url}`);
});
