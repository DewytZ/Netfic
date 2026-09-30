import pg from 'pg';
import config from './config.js';

export function createPool() {
    return new pg.Pool(config.db);
}
