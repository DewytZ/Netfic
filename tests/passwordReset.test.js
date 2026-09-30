import assert from 'node:assert/strict';
import { beforeEach, describe, test } from 'node:test';
import bcrypt from 'bcryptjs';
import { createUser, setup, submitForm } from './helpers.js';

const resetTokenFrom = (mail) => mail.text.match(/reset-password\/([a-f0-9]+)\?/)[1];

describe('restablecer contraseña', () => {
    let ctx;

    beforeEach(() => {
        ctx = setup();
    });

    test('envía el enlace por correo a un usuario registrado', async () => {
        const user = await createUser(ctx.db);
        const agent = ctx.agent();

        await submitForm(agent, '/forgot-password', '/forgot-password', { email: user.email });

        assert.equal(ctx.sentMail.length, 1);
        assert.equal(ctx.sentMail[0].to, user.email);
        assert.match((await agent.get('/forgot-password')).text, /Si el correo está registrado/);
    });

    test('un email desconocido recibe la misma respuesta y no se envía correo', async () => {
        const agent = ctx.agent();

        await submitForm(agent, '/forgot-password', '/forgot-password', { email: 'nadie@example.com' });

        assert.equal(ctx.sentMail.length, 0);
        const page = (await agent.get('/forgot-password')).text;
        assert.match(page, /Si el correo está registrado/);
        assert.doesNotMatch(page, /class="error"/);
    });

    test('no envía otro correo al mismo email antes de un minuto', async () => {
        const user = await createUser(ctx.db);
        const agent = ctx.agent();

        await submitForm(agent, '/forgot-password', '/forgot-password', { email: user.email });
        await submitForm(agent, '/forgot-password', '/forgot-password', { email: user.email });

        assert.equal(ctx.sentMail.length, 1);
    });

    test('restablece la contraseña con un token válido y cierra sus otras sesiones', async () => {
        const user = await createUser(ctx.db);
        const otherDevice = ctx.agent();
        await submitForm(otherDevice, '/login', '/login', { email: user.email, password: user.plainPassword });

        const agent = ctx.agent();
        await submitForm(agent, '/forgot-password', '/forgot-password', { email: user.email });
        const token = resetTokenFrom(ctx.sentMail[0]);
        const formUrl = `/reset-password/${token}?email=${user.email}`;

        const res = await submitForm(agent, formUrl, '/reset-password', {
            token,
            email: user.email,
            password: 'Nueva#Clave9',
            password_confirmation: 'Nueva#Clave9',
        });

        assert.equal(res.headers.location, '/login');
        const { rows } = await ctx.db.query('SELECT password FROM users WHERE id = $1', [user.id]);
        assert.ok(await bcrypt.compare('Nueva#Clave9', rows[0].password));
        assert.equal((await otherDevice.get('/dashboard')).headers.location, '/login');

        const reused = await submitForm(agent, formUrl, '/reset-password', {
            token,
            email: user.email,
            password: 'Otra#Clave99',
            password_confirmation: 'Otra#Clave99',
        });
        assert.notEqual(reused.headers.location, '/login');
    });

    test('rechaza una contraseña débil al restablecer', async () => {
        const user = await createUser(ctx.db);
        const agent = ctx.agent();

        await submitForm(agent, '/reset-password/cualquiera', '/reset-password', {
            token: 'cualquiera',
            email: user.email,
            password: 'debil',
            password_confirmation: 'debil',
        });

        assert.match((await agent.get('/reset-password/cualquiera')).text, /La contraseña debe tener entre 8 y 64 caracteres/);
    });

    test('rechaza un token falso', async () => {
        const user = await createUser(ctx.db);
        const agent = ctx.agent();
        await submitForm(agent, '/forgot-password', '/forgot-password', { email: user.email });

        await submitForm(agent, '/reset-password/token-falso', '/reset-password', {
            token: 'token-falso',
            email: user.email,
            password: 'Nueva#Clave9',
            password_confirmation: 'Nueva#Clave9',
        });

        assert.match((await agent.get('/reset-password/token-falso')).text, /El enlace no es válido o ya expiró/);
    });
});
