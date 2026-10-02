import { useMemo } from 'react';
import { MOVIES } from './movies.js';
import './MovieBackground.css';

const ROWS = [
    { duration: 70, reverse: false },
    { duration: 90, reverse: true },
    { duration: 80, reverse: false },
    { duration: 100, reverse: true },
];

/**
 * Rota la lista para que cada fila empiece por una película distinta.
 */
function moviesForRow(rowIndex) {
    const offset = (rowIndex * 7) % MOVIES.length;

    return [...MOVIES.slice(offset), ...MOVIES.slice(0, offset)];
}

function Poster({ movie }) {
    const [from, to] = movie.colors;

    return (
        <div className="movie-poster" style={{ background: `linear-gradient(160deg, ${from}, ${to})` }}>
            <span className="movie-poster__genre">{movie.genre}</span>
            <span className="movie-poster__title">{movie.title}</span>
        </div>
    );
}

function PosterRow({ movies, duration, reverse }) {
    // La lista va duplicada: al llegar a la mitad, la animación vuelve a empezar sin que se note el salto.
    const loop = [...movies, ...movies];

    return (
        <div
            className={`movie-row${reverse ? ' movie-row--reverse' : ''}`}
            style={{ '--row-duration': `${duration}s` }}
        >
            {loop.map((movie, index) => (
                <Poster key={index} movie={movie} />
            ))}
        </div>
    );
}

/**
 * Fondo decorativo con filas de carátulas que se desplazan. No es interactivo.
 */
export default function MovieBackground() {
    const rows = useMemo(() => ROWS.map((row, index) => ({ ...row, movies: moviesForRow(index) })), []);

    return (
        <div className="movie-background" aria-hidden="true">
            <div className="movie-background__stage">
                {rows.map((row, index) => (
                    <PosterRow key={index} {...row} />
                ))}
            </div>
            <div className="movie-background__shade" />
        </div>
    );
}
