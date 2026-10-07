import { MOVIES } from '../movie-background/movies.js';

/**
 * Catálogo simulado: las películas del fondo animado más algunas series, todo inventado.
 */
const DETAILS = {
    'La Última Frontera': ['Una tripulación varada en el borde del sistema solar recibe una señal que no debería existir.', 2025, '13+', '2 h 08 min'],
    'Ecos del Silencio': ['Tras perder la audición, una pianista reconstruye su vida nota por nota.', 2023, '13+', '1 h 54 min'],
    'Neón Nocturno': ['Un repartidor nocturno se convierte en testigo del golpe más grande de la ciudad.', 2024, '16+', '1 h 47 min'],
    'El Faro': ['Dos guardianes de un faro aislado descubren que la isla guarda un secreto.', 2022, '16+', '1 h 39 min'],
    'Marea Roja': ['Un equipo de rescate tiene doce horas para sacar a un pueblo entero antes del huracán.', 2025, '13+', '2 h 01 min'],
    'Ciudad de Cristal': ['En una ciudad donde todo es visible, una hacker busca el único punto ciego.', 2024, '13+', '1 h 58 min'],
    'Órbita': ['Una ingeniera y un robot de mantenimiento intentan volver a casa desde una estación abandonada.', 2023, '7+', '1 h 44 min'],
    'Los Olvidados': ['Una familia hereda una casa en la que nadie recuerda haber vivido.', 2024, '18+', '1 h 36 min'],
    'Tormenta Solar': ['Una llamarada apaga el planeta y un piloto retirado debe cruzar el continente.', 2025, '13+', '2 h 12 min'],
    'El Jardín Secreto': ['Una niña encuentra una puerta en el jardín de su abuela que lleva a otro mundo.', 2022, '7+', '1 h 41 min'],
    'Código Cero': ['Un programador descubre que el algoritmo que escribió puede predecir crímenes.', 2024, '16+', '1 h 52 min'],
    'Desierto Infinito': ['Tres amigos cruzan el desierto en busca de una ciudad perdida.', 2021, '13+', '1 h 49 min'],
    'Sombras de Tokio': ['Una detective sigue el rastro de un ladrón que nunca aparece en las cámaras.', 2023, '16+', '2 h 04 min'],
    'La Casa del Lago': ['Dos desconocidos se escriben cartas desde la misma casa, separados por diez años.', 2022, '7+', '1 h 46 min'],
    'Viaje al Norte': ['Un recorrido por los últimos glaciares de América y las personas que viven junto a ellos.', 2024, 'TP', '1 h 28 min'],
    'Pulso': ['Un cirujano tiene una noche para salvar al hombre que arruinó su carrera.', 2025, '16+', '1 h 43 min'],
    'Horizonte': ['Un padre y su hijo reconstruyen su relación durante un viaje en velero.', 2023, '7+', '1 h 57 min'],
    'Fuego Lento': ['Una chef y un crítico gastronómico se enamoran en el peor momento posible.', 2024, '13+', '1 h 51 min'],
    'Medianoche': ['Cada noche a las 12, un pueblo entero olvida el día anterior. Menos una persona.', 2025, '13+', '1 h 48 min'],
    'Laberinto': ['Un cartógrafo queda atrapado en un laberinto que cambia cada vez que lo dibuja.', 2023, '13+', '2 h 06 min'],
};

const SERIES = [
    { title: 'Distrito Norte', genre: 'Crimen', colors: ['#7f1d1d', '#111827'], description: 'Una unidad policial enfrenta a la red que controla el puerto más grande del país.', year: 2025, maturity: '16+', duration: '3 temporadas' },
    { title: 'Estación Polar', genre: 'Ciencia ficción', colors: ['#0369a1', '#0c4a6e'], description: 'Doce científicos, una base en la Antártida y algo que despertó bajo el hielo.', year: 2024, maturity: '13+', duration: '2 temporadas' },
    { title: 'Los Herederos', genre: 'Drama', colors: ['#854d0e', '#1c1917'], description: 'La muerte del patriarca desata una guerra entre hermanos por el imperio familiar.', year: 2023, maturity: '16+', duration: '4 temporadas' },
    { title: 'Señal Perdida', genre: 'Misterio', colors: ['#4338ca', '#0f172a'], description: 'Un podcast sobre una desaparición antigua empieza a recibir llamadas del desaparecido.', year: 2025, maturity: '13+', duration: '1 temporada' },
    { title: 'Reino de Ceniza', genre: 'Fantasía', colors: ['#9a3412', '#292524'], description: 'Cinco reinos, un trono vacío y una profecía que nadie quiere cumplir.', year: 2024, maturity: '16+', duration: '2 temporadas' },
    { title: 'Turno de Noche', genre: 'Drama', colors: ['#0f766e', '#042f2e'], description: 'Las historias de un hospital público cuando la ciudad duerme.', year: 2022, maturity: '13+', duration: '5 temporadas' },
    { title: 'Velocidad Máxima', genre: 'Acción', colors: ['#dc2626', '#1f2937'], description: 'Un equipo de pilotos callejeros es reclutado para robar a quienes nadie puede tocar.', year: 2025, maturity: '16+', duration: '2 temporadas' },
    { title: 'Planeta Azul', genre: 'Documental', colors: ['#0284c7', '#082f49'], description: 'Las criaturas más extrañas del océano profundo, filmadas por primera vez.', year: 2023, maturity: 'TP', duration: '1 temporada' },
    { title: 'La Mansión', genre: 'Terror', colors: ['#52525b', '#09090b'], description: 'Seis influencers aceptan pasar un mes en una mansión con una historia oscura.', year: 2024, maturity: '18+', duration: '2 temporadas' },
    { title: 'Amor en Línea', genre: 'Romance', colors: ['#db2777', '#500724'], description: 'Una app de citas empareja a dos personas que se odian en la vida real.', year: 2025, maturity: '13+', duration: '1 temporada' },
];

// Coincidencia "estable" por título para que no cambie en cada recarga.
const matchFor = (title) => 80 + ([...title].reduce((sum, char) => sum + char.charCodeAt(0), 0) % 20);

export const CATALOG = [
    ...MOVIES.map((movie) => {
        const [description, year, maturity, duration] = DETAILS[movie.title];
        return { ...movie, type: 'Película', description, year, maturity, duration };
    }),
    ...SERIES.map((serie) => ({ ...serie, type: 'Serie' })),
].map((item, index) => ({ ...item, id: index + 1, match: matchFor(item.title) }));

export const byTitle = (...titles) => titles.map((title) => CATALOG.find((item) => item.title === title)).filter(Boolean);

export const byGenres = (...genres) => CATALOG.filter((item) => genres.includes(item.genre));

/** Filas de la pantalla de inicio, en orden. */
export const HOME_ROWS = [
    { id: 'continue', title: 'Continuar viendo', items: byTitle('Señal Perdida', 'Neón Nocturno', 'Turno de Noche', 'Órbita', 'Reino de Ceniza'), progress: [62, 35, 80, 15, 48] },
    { id: 'trending', title: 'Tendencias ahora', items: byTitle('Tormenta Solar', 'Distrito Norte', 'Medianoche', 'Pulso', 'Estación Polar', 'Marea Roja', 'Amor en Línea', 'La Última Frontera', 'Velocidad Máxima') },
    { id: 'top10', title: 'Top 10 en México hoy', items: byTitle('Distrito Norte', 'Tormenta Solar', 'Los Herederos', 'Medianoche', 'Código Cero', 'La Mansión', 'Ciudad de Cristal', 'Fuego Lento', 'Sombras de Tokio', 'Pulso'), ranked: true },
    { id: 'action', title: 'Acción y aventura', items: byGenres('Acción', 'Aventura') },
    { id: 'suspense', title: 'Suspenso que no te deja dormir', items: byGenres('Thriller', 'Misterio', 'Crimen') },
    { id: 'scifi', title: 'Ciencia ficción y fantasía', items: byGenres('Ciencia ficción', 'Fantasía') },
    { id: 'drama', title: 'Dramas y romances', items: byGenres('Drama', 'Romance') },
    { id: 'new', title: 'Estrenos 2025', items: CATALOG.filter((item) => item.year === 2025) },
    { id: 'family', title: 'Para ver en familia', items: CATALOG.filter((item) => ['TP', '7+'].includes(item.maturity)) },
];
