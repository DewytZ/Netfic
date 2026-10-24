/**
 * Catálogo del dashboard: contenido de MongoDB (Internet Archive y frutinovelas de YouTube)
 * y títulos de la tabla titles. Solo se muestra lo que tiene un video real (ver isPlayable).
 */

const youTubeIdFrom = (url) => url?.match(/embed\/([\w-]{11})/)?.[1] ?? null;

// Tiene video que se pueda reproducir: MP4 directo o reproductor de YouTube insertado.
export const isPlayable = (item) => Boolean(item.videoUrl || item.embedUrl);

const DEFAULT_COLORS = ['#1f2937', '#111827'];

// Coincidencia "estable" por título para que no cambie en cada recarga.
const matchFor = (title) => 80 + ([...title].reduce((sum, char) => sum + char.charCodeAt(0), 0) % 20);

// Las series muestran sus temporadas. Las guardadas antes tenían el texto en "duration", por eso ese es el respaldo.
const durationLabel = (title) => {
    if (title.type === 'Serie' && title.seasons) {
        return `${title.seasons} ${title.seasons === 1 ? 'temporada' : 'temporadas'}`;
    }
    return title.duration ?? '';
};

/**
 * Convierte los registros de la base de datos en elementos del catálogo.
 * @param {Array<object>} titles registros de la tabla titles
 */
export function buildCatalog(titles) {
    return titles.map((title) => ({
        id: `title-${title.id}`,
        title: title.title,
        genre: title.genre,
        genres: title.genre ? [title.genre] : [],
        type: title.type,
        year: title.year,
        maturity: title.maturity,
        source: 'netfic',
        duration: durationLabel(title),
        description: title.description ?? '',
        // Si no tiene colores, usamos unos neutros para la carátula.
        colors: title.colors?.length >= 2 ? title.colors : DEFAULT_COLORS,
        match: matchFor(title.title),
    }));
}

// Paletas para las carátulas del contenido de MongoDB (no guarda colores propios).
const CONTENT_PALETTES = [
    ['#7c2d12', '#1c1917'], ['#1e3a8a', '#0f172a'], ['#14532d', '#052e16'], ['#581c87', '#1e1b4b'],
    ['#9f1239', '#270a16'], ['#854d0e', '#1c1917'], ['#155e75', '#082f49'], ['#3f3f46', '#09090b'],
];

const minutesLabel = (minutes) => {
    if (!minutes) return '';
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    return h ? `${h} h${m ? ` ${m} min` : ''}` : `${m} min`;
};

/**
 * Convierte los documentos de MongoDB (colección contents) en elementos del catálogo.
 * Las series y novelas se muestran como "Serie"; películas, cortos y documentales como "Película".
 * @param {Array<object>} contents documentos publicados de la colección contents
 */
export function buildContentCatalog(contents) {
    return contents.map((content) => {
        // Un capítulo puede ser un tramo de un video de YouTube: { videoId, start, end } en segundos.
        const episodes = (content.episodes ?? []).map((ep) => ({
            season: ep.season,
            number: ep.number,
            title: ep.title,
            duration: ep.duration ?? null,
            videoUrl: ep.video_url ?? null,
            embedUrl: ep.embed_url ?? null,
            videoId: ep.video_id ?? youTubeIdFrom(ep.embed_url),
            start: ep.start ?? null,
            end: ep.end ?? null,
            thumbnail: ep.thumbnail_url ?? null,
        }));
        const isSeries = ['serie', 'novela'].includes(content.type);
        const seed = matchFor(content.title);

        return {
            id: `content-${content.id}`,
            title: content.title,
            genre: content.genres?.[0] ?? 'Clásico',
            genres: content.genres ?? [],
            tags: content.tags ?? [],
            type: isSeries ? 'Serie' : 'Película',
            year: content.year,
            maturity: content.maturity ?? (content.source === 'internet_archive' ? 'Clásico' : 'TP'),
            // Una frutinovela completa en un solo video no tiene capítulos: se muestra su duración.
            duration: isSeries && episodes.length
                ? `${episodes.length} ${episodes.length === 1 ? 'capítulo' : 'capítulos'}`
                : minutesLabel(content.duration),
            description: content.synopsis ?? '',
            colors: CONTENT_PALETTES[seed % CONTENT_PALETTES.length],
            match: seed,
            source: content.source,
            poster: content.poster_url ?? null,
            videoUrl: content.video_url ?? episodes.find((ep) => ep.videoUrl)?.videoUrl ?? null,
            // Reproductor oficial de YouTube (frutinovelas): el video nunca se descarga.
            embedUrl: content.embed_url ?? episodes.find((ep) => ep.embedUrl)?.embedUrl ?? null,
            videoId: youTubeIdFrom(content.embed_url),
            channel: content.source_ref?.channel ?? null,
            channelUrl: content.source_ref?.channel_url ?? null,
            originalUrl: content.source_ref?.url ?? null,
            episodes,
        };
    });
}

/**
 * Filas de la pantalla de inicio. Los títulos de cada fila salen del catálogo recibido.
 * @param {Array<object>} catalog resultado de buildCatalog
 */
export function buildHomeRows(catalog) {
    // Un título entra en la fila si tiene cualquiera de los géneros (el contenido de MongoDB puede tener varios).
    const byGenres = (...genres) => catalog.filter((item) => item.genres.some((genre) => genres.includes(genre)));

    return [
        // El catálogo llega ordenado del más nuevo al más viejo (DashboardController).
        { id: 'recent', title: 'Recién agregadas', items: catalog.slice(0, 12) },
        { id: 'frutinovelas', title: 'Frutinovelas', items: catalog.filter((item) => ['frutinovela', 'ia'].includes(item.source)) },
        { id: 'archive', title: 'Clásicos de dominio público', items: catalog.filter((item) => item.source === 'internet_archive') },
        { id: 'action', title: 'Acción y aventura', items: byGenres('Acción', 'Aventura') },
        { id: 'comedy', title: 'Comedias para reír', items: byGenres('Comedia') },
        { id: 'suspense', title: 'Suspenso que no te deja dormir', items: byGenres('Thriller', 'Misterio', 'Crimen', 'Cine negro') },
        { id: 'horror', title: 'Terror', items: byGenres('Terror') },
        { id: 'western', title: 'Westerns y cine bélico', items: byGenres('Western', 'Bélico') },
        { id: 'scifi', title: 'Ciencia ficción y fantasía', items: byGenres('Ciencia ficción', 'Fantasía') },
        { id: 'drama', title: 'Dramas y romances', items: byGenres('Drama', 'Romance') },
        { id: 'family', title: 'Para ver en familia', items: catalog.filter((item) => ['TP', '7+'].includes(item.maturity)) },
    ];
}

/* ---------- Filtros (Series, Películas y Explorar) ---------- */

export const SOURCE_OPTIONS = [
    { id: '', label: 'Todo el catálogo' },
    { id: 'frutinovela', label: 'Frutinovelas' },
    { id: 'internet_archive', label: 'Clásicos de dominio público' },
];

export const SORT_OPTIONS = [
    { id: 'match', label: 'Recomendados' },
    { id: 'newest', label: 'Más recientes' },
    { id: 'oldest', label: 'Más antiguos' },
    { id: 'az', label: 'A-Z' },
];

export const EMPTY_FILTERS = { genre: '', decade: '', source: '', sort: 'match' };

export const hasActiveFilters = (filters) => Boolean(filters.genre || filters.decade || filters.source || filters.sort !== 'match');

// Géneros presentes en el catálogo, con cuántos títulos tiene cada uno (los más comunes primero).
export function genreCounts(catalog) {
    const counts = new Map();
    catalog.forEach((item) => item.genres.forEach((genre) => counts.set(genre, (counts.get(genre) ?? 0) + 1)));
    return [...counts].sort((a, b) => b[1] - a[1] || a[0].localeCompare(b[0], 'es')).map(([genre, count]) => ({ genre, count }));
}

// Décadas presentes, de la más reciente a la más antigua (1930, 1940...).
export function decadesOf(catalog) {
    return [...new Set(catalog.filter((item) => item.year).map((item) => Math.floor(item.year / 10) * 10))].sort((a, b) => b - a);
}

const sameSource = (item, source) => (source === 'frutinovela' ? ['frutinovela', 'ia'].includes(item.source) : item.source === source);

/**
 * @param {Array<object>} catalog
 * @param {{genre: string, decade: string|number, source: string, sort: string}} filters
 */
export function filterCatalog(catalog, filters) {
    const decade = Number(filters.decade);
    const items = catalog.filter((item) => (!filters.genre || item.genres.includes(filters.genre))
        && (!decade || (item.year >= decade && item.year < decade + 10))
        && (!filters.source || sameSource(item, filters.source)));

    const sorters = {
        match: (a, b) => b.match - a.match,
        newest: (a, b) => (b.year ?? 0) - (a.year ?? 0),
        oldest: (a, b) => (a.year ?? 9999) - (b.year ?? 9999),
        az: (a, b) => a.title.localeCompare(b.title, 'es'),
    };

    return items.sort(sorters[filters.sort] ?? sorters.match);
}
