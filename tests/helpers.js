import fs from 'node:fs';
import bcrypt from 'bcryptjs';
import session from 'express-session';
import { newDb } from 'pg-mem';
import request from 'supertest';
import { createApp } from '../src/app.js';

export function setup() {
    const mem = newDb();
    mem.public.none(fs.readFileSync(new URL('../database/schema.sql', import.meta.url), 'utf8'));

    const { Pool } = mem.adapters.createPg();
    const db = new Pool();
    const sentMail = [];
    const mailer = { send: async (message) => sentMail.push(message) };

    const app = createApp({ db, mailer, sessionStore: new session.MemoryStore(), sessionSecret: 'test-secret' });

    return { app, db, sentMail, agent: () => request.agent(app) };
}

export async function createUser(db, { name = 'Ana', email = 'ana@example.com', password = 'Secreto#123' } = {}) {
    const hash = await bcrypt.hash(password, 4);
    const { rows } = await db.query(
        'INSERT INTO users (name, email, password) VALUES ($1, $2, $3) RETURNING *',
        [name, email, hash],
    );

    return { ...rows[0], plainPassword: password };
}

/**
 * Abre la página del formulario, toma el token CSRF y envía el POST con la misma sesión.
 */
export async function submitForm(agent, formUrl, postUrl, fields) {
    const page = await agent.get(formUrl);
    const token = page.text.match(/name="_token" value="([^"]+)"/)?.[1];

    return agent.post(postUrl).type('form').send({ _token: token, ...fields });
}
