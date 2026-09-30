import assert from 'node:assert/strict';
import { beforeEach, describe, test } from 'node:test';
import { createUser, setup, submitForm } from './helpers.js';

describe('autenticación', () => {
    let ctx;

    beforeEach(() => {
        ctx = setup();
    });

    test('un invitado es redirigido al login', async () => {
        const res = await ctx.agent().get('/dashboard');

        assert.equal(res.status, 302);
        assert.equal(res.headers.location, '/login');
    });

    test('un usuario se puede registrar', async () => {
        const agent = ctx.agent();
        const res = await submitForm(agent, '/register', '/register', {
            name: 'Ana',
            email: 'ana@example.com',
            password: 'Secreto#123',
            password_confirmation: 'Secreto#123',
        });

        assert.equal(res.headers.location, '/dashboard');
        const { rows } = await ctx.db.query('SELECT email FROM users');
        assert.deepEqual(rows, [{ email: 'ana@example.com' }]);
        assert.match((await agent.get('/dashboard')).text, /Cuenta creada correctamente/);
    });

    test('no permite registrar dos veces el mismo email', async () => {
        await createUser(ctx.db);
        const agent = ctx.agent();
        await submitForm(agent, '/register', '/register', {
            name: 'Otra',
            email: 'ana@example.com',
            password: 'Secreto#123',
            password_confirmation: 'Secreto#123',
        });

        assert.match((await agent.get('/register')).text, /Ese email ya está registrado/);
    });

    const weakPasswords = {
        'muy corta': 'Ab#1',
        'sin mayúscula': 'secreto#123',
        'sin minúscula': 'SECRETO#123',
        'sin número': 'Secreto#abc',
        'sin símbolo': 'Secreto1234',
        'con espacio': 'Secreto #123',
    };

    for (const [caso, password] of Object.entries(weakPasswords)) {
        test(`rechaza una contraseña débil: ${caso}`, async () => {
            const agent = ctx.agent();
            const res = await submitForm(agent, '/register', '/register', {
                name: 'Ana',
                email: 'ana@example.com',
                password,
                password_confirmation: password,
            });

            assert.equal(res.headers.location, '/register');
            assert.match((await agent.get('/register')).text, /La contraseña debe tener entre 8 y 64 caracteres/);
            const { rows } = await ctx.db.query('SELECT 1 FROM users');
            assert.equal(rows.length, 0);
        });
    }

    test('un usuario puede iniciar y cerrar sesión', async () => {
        const user = await createUser(ctx.db);
        const agent = ctx.agent();

        const login = await submitForm(agent, '/login', '/login', { email: user.email, password: user.plainPassword });
        assert.equal(login.headers.location, '/dashboard');

        const dashboard = await agent.get('/dashboard');
        assert.equal(dashboard.status, 200);
        assert.match(dashboard.text, /Ana/);

        const logout = await submitForm(agent, '/dashboard', '/logout', {});
        assert.equal(logout.headers.location, '/login');
        assert.equal((await agent.get('/dashboard')).headers.location, '/login');
    });

    test('el login falla con una contraseña incorrecta', async () => {
        const user = await createUser(ctx.db);
        const agent = ctx.agent();

        const res = await submitForm(agent, '/login', '/login', { email: user.email, password: 'Incorrecta#1' });

        assert.equal(res.headers.location, '/login');
        assert.match((await agent.get('/login')).text, /Las credenciales no son correctas/);
        assert.equal((await agent.get('/dashboard')).headers.location, '/login');
    });

    test('tras el login vuelve a la página que se quería visitar', async () => {
        const user = await createUser(ctx.db);
        const agent = ctx.agent();
        await agent.get('/dashboard');

        const res = await submitForm(agent, '/login', '/login', { email: user.email, password: user.plainPassword });

        assert.equal(res.headers.location, '/dashboard');
    });

    test('limita el login a 5 intentos por minuto', async () => {
        const agent = ctx.agent();
        let res;
        for (let i = 0; i < 6; i++) {
            res = await submitForm(agent, '/login', '/login', { email: 'x@example.com', password: 'Incorrecta#1' });
        }

        assert.equal(res.status, 429);
    });

    test('rechaza un POST sin token CSRF', async () => {
        const res = await ctx.agent().post('/login').type('form').send({ email: 'a@b.com', password: 'x' });

        assert.equal(res.status, 419);
    });
});
