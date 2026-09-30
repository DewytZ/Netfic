import fs from 'node:fs';
import { createPool } from '../src/db.js';

const pool = createPool();

try {
    await pool.query(fs.readFileSync(new URL('../database/schema.sql', import.meta.url), 'utf8'));
    console.log('Tablas creadas: users, password_reset_tokens, session');
} catch (error) {
    console.error('Error al migrar:', error.message);
    process.exitCode = 1;
} finally {
    await pool.end();
}
