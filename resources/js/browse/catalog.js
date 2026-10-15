/**
 * Catálogo del dashboard. Todos los títulos (películas y series) llegan de la base de datos
 * (tabla titles), desde DashboardController.
 */

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
        type: title.type,
        year: title.year,
        maturity: title.maturity,
        duration: durationLabel(title),
        description: title.description ?? '',
        // Si no tiene colores, usamos unos neutros para la carátula.
        colors: title.colors?.length >= 2 ? title.colors : DEFAULT_COLORS,
        match: matchFor(title.title),
    }));
}

/**
 * Filas de la pantalla de inicio. Los títulos de cada fila salen del catálogo recibido.
 * @param {Array<object>} catalog resultado de buildCatalog
 */
export function buildHomeRows(catalog) {
    const byTitle = (...titles) => titles.map((title) => catalog.find((item) => item.title === title)).filter(Boolean);
    const byGenres = (...genres) => catalog.filter((item) => genres.includes(item.genre));

    return [
        { id: 'continue', title: 'Continuar viendo', items: byTitle('Señal Perdida', 'Neón Nocturno', 'Turno de Noche', 'Órbita', 'Reino de Ceniza'), progress: [62, 35, 80, 15, 48] },
        { id: 'trending', title: 'Tendencias ahora', items: byTitle('Tormenta Solar', 'Distrito Norte', 'Medianoche', 'Pulso', 'Estación Polar', 'Marea Roja', 'Amor en Línea', 'La Última Frontera', 'Velocidad Máxima') },
        { id: 'top10', title: 'Top 10 en México hoy', items: byTitle('Distrito Norte', 'Tormenta Solar', 'Los Herederos', 'Medianoche', 'Código Cero', 'La Mansión', 'Ciudad de Cristal', 'Fuego Lento', 'Sombras de Tokio', 'Pulso'), ranked: true },
        { id: 'action', title: 'Acción y aventura', items: byGenres('Acción', 'Aventura') },
        { id: 'suspense', title: 'Suspenso que no te deja dormir', items: byGenres('Thriller', 'Misterio', 'Crimen') },
        { id: 'scifi', title: 'Ciencia ficción y fantasía', items: byGenres('Ciencia ficción', 'Fantasía') },
        { id: 'drama', title: 'Dramas y romances', items: byGenres('Drama', 'Romance') },
        { id: 'new', title: 'Estrenos 2025', items: catalog.filter((item) => item.year === 2025) },
        { id: 'family', title: 'Para ver en familia', items: catalog.filter((item) => ['TP', '7+'].includes(item.maturity)) },
    ];
}
