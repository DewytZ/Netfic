import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useOverlay } from './overlay.js';

/**
 * Reproductor de Netfic con controles propios.
 *
 * - Frutinovelas: reproductor oficial de YouTube (IFrame API) sin sus controles. Las políticas de YouTube
 *   no permiten tapar, recortar ni ocultar partes de su reproductor, así que nada de Netfic va ENCIMA del
 *   iframe: los controles van debajo y la carátula de carga queda DETRÁS (el iframe la cubre al aparecer).
 *   Para que se sienta propio sin romper esas reglas:
 *     · la API se precarga al abrir el catálogo (preloadYouTubeApi), así el video arranca casi al instante;
 *     · se pasa al siguiente capítulo (o se cierra el video) un instante antes del final, así nunca aparece
 *       la pantalla de "videos sugeridos" de YouTube;
 *     · si el navegador bloquea el autoplay con sonido, se reintenta en silencio con un botón "Activar sonido"
 *       (en vez del botón rojo de YouTube).
 * - Clásicos de Internet Archive: <video> con el MP4, con los mismos controles.
 *
 * Un capítulo puede ser un tramo de un video ({ videoId, start, end } en segundos): así las
 * recopilaciones de una hora se ven como capítulos de ~8 minutos sin descargar ni cortar nada.
 */

const YT_HOST = 'https://www.youtube-nocookie.com';
const YT_UNSTARTED = -1;
const YT_ENDED = 0;
const YT_PLAYING = 1;
const YT_CUED = 5;

// Se sale del video este tanto antes del final para que YouTube no llegue a mostrar sus sugerencias.
const END_MARGIN = 0.4;
const AUTOPLAY_CHECK_MS = 2500;
const IDLE_MS = 3000;
const RATES = [0.5, 0.75, 1, 1.25, 1.5, 2];

const API_TIMEOUT_MS = 10000;
const START_TIMEOUT_MS = 15000;

let youTubeApi;

// Carga la IFrame API de YouTube una sola vez. Falla si el script no carga (bloqueador, sin red) o tarda demasiado;
// en ese caso se puede volver a intentar.
function loadYouTubeApi() {
    youTubeApi ??= new Promise((resolve, reject) => {
        if (window.YT?.Player) {
            resolve(window.YT);
            return;
        }
        const timer = setTimeout(() => reject(new Error('timeout')), API_TIMEOUT_MS);
        const previous = window.onYouTubeIframeAPIReady;
        window.onYouTubeIframeAPIReady = () => {
            previous?.();
            clearTimeout(timer);
            resolve(window.YT);
        };
        const script = document.createElement('script');
        script.src = 'https://www.youtube.com/iframe_api';
        script.onerror = () => {
            clearTimeout(timer);
            script.remove();
            reject(new Error('blocked'));
        };
        document.head.appendChild(script);
    }).catch((error) => {
        youTubeApi = undefined;
        throw error;
    });
    return youTubeApi;
}

// Mensajes para los códigos de error de YouTube (onError) y los propios.
const ERRORS = {
    api: 'No se pudo cargar el reproductor de YouTube. Suele pasar cuando un bloqueador de anuncios, la protección contra rastreo del navegador o la red bloquean youtube.com.',
    timeout: 'El video no arrancó. Puede que un bloqueador de anuncios o la protección contra rastreo del navegador lo estén impidiendo.',
    100: 'Este video ya no existe en YouTube o ahora es privado.',
    101: 'El canal no permite ver este video fuera de YouTube.',
    150: 'El canal no permite ver este video fuera de YouTube.',
    153: 'YouTube rechazó el reproductor porque el sitio no envió la cabecera Referer.',
    mp4: 'No se pudo cargar el video. Puede que el archivo ya no esté disponible en Internet Archive.',
    default: 'YouTube no pudo reproducir este video.',
};

// Se llama al abrir el catálogo: al dar "Reproducir" la API ya está lista y el video arranca enseguida.
export const preloadYouTubeApi = () => { loadYouTubeApi(); };

const clock = (seconds) => {
    const s = Math.max(0, Math.floor(seconds || 0));
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const sec = String(s % 60).padStart(2, '0');
    return h ? `${h}:${String(m).padStart(2, '0')}:${sec}` : `${m}:${sec}`;
};

/* ---------- Íconos ---------- */

const Svg = ({ children, size = 24, fill = 'none' }) => (
    <svg viewBox="0 0 24 24" width={size} height={size} fill={fill} stroke={fill === 'none' ? 'currentColor' : 'none'} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
        {children}
    </svg>
);
const PlayIcon = () => <Svg fill="currentColor"><path d="M7 4.5v15a1 1 0 0 0 1.5.86l12.5-7.5a1 1 0 0 0 0-1.72L8.5 3.64A1 1 0 0 0 7 4.5z" /></Svg>;
const PauseIcon = () => <Svg fill="currentColor"><rect x="6" y="4" width="4" height="16" rx="1" /><rect x="14" y="4" width="4" height="16" rx="1" /></Svg>;
const BackIcon = () => <Svg size={28}><path d="M19 12H5M12 19l-7-7 7-7" /></Svg>;
const Rewind = () => <Svg><path d="M3 12a9 9 0 1 0 3-6.7L3 8" /><path d="M3 3v5h5" /><text x="12" y="15.5" fontSize="7" textAnchor="middle" fill="currentColor" stroke="none">10</text></Svg>;
const Forward = () => <Svg><path d="M21 12a9 9 0 1 1-3-6.7L21 8" /><path d="M21 3v5h-5" /><text x="12" y="15.5" fontSize="7" textAnchor="middle" fill="currentColor" stroke="none">10</text></Svg>;
const VolumeIcon = ({ muted }) => (
    <Svg>
        <path d="M11 5 6 9H2v6h4l5 4z" />
        {muted ? <path d="m23 9-6 6M17 9l6 6" /> : <path d="M15.5 8.5a5 5 0 0 1 0 7M19 5a10 10 0 0 1 0 14" />}
    </Svg>
);
const NextIcon = () => <Svg fill="currentColor"><path d="M5 4.5v15a1 1 0 0 0 1.5.86L17 14V19a1 1 0 0 0 2 0V5a1 1 0 0 0-2 0v5L6.5 3.64A1 1 0 0 0 5 4.5z" /></Svg>;
const ListIcon = () => <Svg><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" /></Svg>;
const FullscreenIcon = ({ active }) => (
    <Svg>{active ? <path d="M8 3v3a2 2 0 0 1-2 2H3m18 0h-3a2 2 0 0 1-2-2V3m0 18v-3a2 2 0 0 1 2-2h3M3 16h3a2 2 0 0 1 2 2v3" /> : <path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3" />}</Svg>
);

/* ---------- Motores: YouTube y MP4 con la misma interfaz ---------- */

function useYouTubeEngine(hostRef, source, attempt, { onPlayingChange, onEnded, onStarted, onAutoMuted, onError }) {
    const playerRef = useRef(null);
    const callbacks = useRef({ onPlayingChange, onEnded, onStarted, onAutoMuted, onError });
    callbacks.current = { onPlayingChange, onEnded, onStarted, onAutoMuted, onError };

    // Reintento: el contenedor del iframe se vuelve a montar (key = attempt) y se crea un reproductor nuevo.
    useEffect(() => {
        if (attempt === 0) return;
        try { playerRef.current?.destroy?.(); } catch { /* el iframe ya no está */ }
        playerRef.current = null;
    }, [attempt]);
    const autoplayTimer = useRef(null);

    // Si el navegador bloqueó el autoplay con sonido, el video sigue sin empezar: se reintenta en silencio.
    const checkAutoplay = useCallback(() => {
        clearTimeout(autoplayTimer.current);
        autoplayTimer.current = setTimeout(() => {
            const player = playerRef.current;
            const state = player?.getPlayerState?.();
            if (state === YT_UNSTARTED || state === YT_CUED) {
                player.mute();
                player.playVideo();
                callbacks.current.onAutoMuted();
            }
        }, AUTOPLAY_CHECK_MS);
    }, []);

    useEffect(() => {
        if (!source?.videoId) return undefined;
        let cancelled = false;
        const params = { videoId: source.videoId, startSeconds: source.start ?? 0, endSeconds: source.end ?? undefined };

        if (playerRef.current) {
            playerRef.current.loadVideoById(params);
            checkAutoplay();
            return undefined;
        }

        loadYouTubeApi().then((YT) => {
            if (cancelled || !hostRef.current) return;
            playerRef.current = new YT.Player(hostRef.current, {
                host: YT_HOST,
                videoId: source.videoId,
                width: '100%',
                height: '100%',
                playerVars: {
                    autoplay: 1,
                    controls: 0,
                    disablekb: 1,
                    fs: 0,
                    rel: 0,
                    iv_load_policy: 3,
                    cc_load_policy: 0,
                    playsinline: 1,
                    hl: 'es',
                    start: source.start ?? undefined,
                    end: source.end ?? undefined,
                    origin: window.location.origin,
                },
                events: {
                    onReady: (event) => {
                        event.target.playVideo();
                        checkAutoplay();
                    },
                    onStateChange: (event) => {
                        callbacks.current.onPlayingChange(event.data === YT_PLAYING);
                        if (event.data === YT_PLAYING) callbacks.current.onStarted();
                        if (event.data === YT_ENDED) callbacks.current.onEnded();
                    },
                    onError: (event) => {
                        clearTimeout(autoplayTimer.current);
                        callbacks.current.onError(event.data);
                    },
                },
            });
        }).catch(() => {
            if (!cancelled) callbacks.current.onError('api');
        });

        return () => { cancelled = true; };
    }, [hostRef, source?.videoId, source?.start, source?.end, checkAutoplay, attempt]);

    useEffect(() => () => {
        clearTimeout(autoplayTimer.current);
        playerRef.current?.destroy?.();
    }, []);

    const p = () => playerRef.current;
    return useMemo(() => ({
        play: () => p()?.playVideo?.(),
        pause: () => p()?.pauseVideo?.(),
        seek: (seconds) => p()?.seekTo?.(seconds, true),
        time: () => p()?.getCurrentTime?.() ?? 0,
        duration: () => p()?.getDuration?.() ?? 0,
        setMuted: (muted) => (muted ? p()?.mute?.() : p()?.unMute?.()),
        setVolume: (volume) => p()?.setVolume?.(volume),
        setRate: (rate) => p()?.setPlaybackRate?.(rate),
    }), []);
}

function useVideoEngine(videoRef) {
    return useMemo(() => ({
        play: () => videoRef.current?.play().catch(() => {}),
        pause: () => videoRef.current?.pause(),
        seek: (seconds) => { if (videoRef.current) videoRef.current.currentTime = seconds; },
        time: () => videoRef.current?.currentTime ?? 0,
        duration: () => videoRef.current?.duration || 0,
        setMuted: (muted) => { if (videoRef.current) videoRef.current.muted = muted; },
        setVolume: (volume) => { if (videoRef.current) videoRef.current.volume = volume / 100; },
        setRate: (rate) => { if (videoRef.current) videoRef.current.playbackRate = rate; },
    }), [videoRef]);
}

/* ---------- Reproductor ---------- */

/**
 * @param {{ item: object, startIndex?: number, onClose: () => void }} props
 */
export default function Player({ item, startIndex = 0, onClose }) {
    const closeRef = useOverlay(onClose);
    const rootRef = useRef(null);
    const ytHostRef = useRef(null);
    const videoRef = useRef(null);

    // Lista de lo que se puede reproducir: los capítulos o el título completo.
    const sources = useMemo(() => {
        const episodes = (item.episodes ?? []).filter((ep) => ep.videoUrl || ep.videoId);
        if (episodes.length) return episodes;
        return [{ title: item.title, videoUrl: item.videoUrl, videoId: item.videoId, start: null, end: null }];
    }, [item]);
    const isSeries = sources.length > 1 || Boolean(item.episodes?.length);

    const [index, setIndex] = useState(Math.min(startIndex, sources.length - 1));
    const [playing, setPlaying] = useState(false);
    const [started, setStarted] = useState(false);
    const [muted, setMuted] = useState(false);
    const [autoMuted, setAutoMuted] = useState(false);
    const [volume, setVolume] = useState(100);
    const [rate, setRate] = useState(1);
    const [time, setTime] = useState(0);
    const [length, setLength] = useState(0);
    const [listOpen, setListOpen] = useState(false);
    const [fullscreen, setFullscreen] = useState(false);
    const [finished, setFinished] = useState(false);
    const [idle, setIdle] = useState(false);
    const [error, setError] = useState(null);
    const [attempt, setAttempt] = useState(0);

    const source = sources[index];
    const offset = source.start ?? 0;
    const hasNext = index < sources.length - 1;
    const isYouTube = !source.videoUrl;

    // El fin de un tramo se detecta varias veces (revisión del tiempo y evento "ended"):
    // se avanza una sola vez por capítulo.
    const advancedFrom = useRef(-1);
    const switchedAt = useRef(Date.now());

    useEffect(() => {
        switchedAt.current = Date.now();
        advancedFrom.current = -1;
    }, [index]);

    const next = useCallback(() => {
        if (advancedFrom.current === index) return;
        advancedFrom.current = index;

        if (index < sources.length - 1) {
            setFinished(false);
            setIndex(index + 1);
        } else {
            setPlaying(false);
            setFinished(true);
        }
    }, [index, sources.length]);

    const onAutoMuted = useCallback(() => {
        setMuted(true);
        setAutoMuted(true);
    }, []);

    const youTube = useYouTubeEngine(ytHostRef, isYouTube ? source : null, attempt, {
        onPlayingChange: setPlaying,
        onEnded: next,
        onStarted: () => { setStarted(true); setError(null); },
        onAutoMuted,
        onError: (code) => { setPlaying(false); setError(code); },
    });
    const html5 = useVideoEngine(videoRef);
    const engine = isYouTube ? youTube : html5;

    // Al terminar todo se pausa y el video se quita de la vista: la pantalla final es la de Netfic.
    useEffect(() => {
        if (finished) engine.pause();
    }, [finished, engine]);

    // Avance: tiempo relativo al capítulo, y salto al siguiente un instante antes del final del tramo.
    useEffect(() => {
        const timer = setInterval(() => {
            const absolute = engine.time();
            const end = source.end ?? engine.duration();
            setTime(Math.max(0, absolute - offset));
            setLength(Math.max(0, end - offset));
            // Recién cambiado de capítulo el tiempo aún es el del tramo anterior: se ignora un momento.
            const settled = Date.now() - switchedAt.current > 1500;
            if (settled && end > 0 && absolute >= end - END_MARGIN && absolute <= end + 2) next();
        }, 250);
        return () => clearInterval(timer);
    }, [engine, source, offset, next]);

    useEffect(() => {
        setTime(0);
        setStarted(false);
        setError(null);
    }, [index]);

    // Si el video no arranca en un rato (y nadie avisó un error), se dice en vez de dejar la carga girando.
    useEffect(() => {
        if (started || error || finished) return undefined;
        const timer = setTimeout(() => setError('timeout'), START_TIMEOUT_MS);
        return () => clearTimeout(timer);
    }, [started, error, finished, index, attempt]);

    const retry = useCallback(() => {
        setError(null);
        setStarted(false);
        switchedAt.current = Date.now();
        advancedFrom.current = -1;
        setAttempt((n) => n + 1);
    }, []);

    const watchUrl = source.videoId
        ? `https://www.youtube.com/watch?v=${source.videoId}${source.start ? `&t=${source.start}s` : ''}`
        : item.originalUrl ?? source.videoUrl;

    const toggle = useCallback(() => {
        if (finished) {
            setFinished(false);
            advancedFrom.current = -1;
            switchedAt.current = Date.now();
            setIndex(0);
            engine.seek(sources[0].start ?? 0);
            engine.play();
            return;
        }
        playing ? engine.pause() : engine.play();
    }, [engine, playing, finished, sources]);

    // Nunca se salta hasta el final exacto (ahí YouTube mostraría sus sugerencias).
    const clampSeek = useCallback((relative) => {
        const target = Math.max(offset, offset + relative);
        engine.seek(length > END_MARGIN ? Math.min(target, offset + length - END_MARGIN) : target);
    }, [engine, offset, length]);

    const skip = useCallback((delta) => clampSeek(time + delta), [clampSeek, time]);

    const toggleMute = useCallback(() => {
        engine.setMuted(!muted);
        setMuted(!muted);
        setAutoMuted(false);
        if (muted && volume === 0) {
            setVolume(50);
            engine.setVolume(50);
        }
    }, [engine, muted, volume]);

    const changeVolume = useCallback((value) => {
        setVolume(value);
        engine.setVolume(value);
        engine.setMuted(value === 0);
        setMuted(value === 0);
        setAutoMuted(false);
    }, [engine]);

    const cycleRate = useCallback(() => {
        const nextRate = RATES[(RATES.indexOf(rate) + 1) % RATES.length];
        setRate(nextRate);
        engine.setRate(nextRate);
    }, [engine, rate]);

    // Velocidad y volumen se mantienen al cambiar de capítulo.
    useEffect(() => {
        engine.setRate(rate);
        engine.setVolume(volume);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [index, started]);

    const toggleFullscreen = useCallback(() => {
        if (document.fullscreenElement) document.exitFullscreen();
        else rootRef.current?.requestFullscreen?.();
    }, []);

    useEffect(() => {
        const onChange = () => setFullscreen(Boolean(document.fullscreenElement));
        document.addEventListener('fullscreenchange', onChange);
        return () => document.removeEventListener('fullscreenchange', onChange);
    }, []);

    // Los controles y el cursor se esconden tras unos segundos sin mover el mouse (solo mientras se reproduce).
    // Se esconden con opacidad, sin cambiar el tamaño del video.
    const idleTimer = useRef(null);
    const wake = useCallback(() => {
        setIdle(false);
        clearTimeout(idleTimer.current);
        idleTimer.current = setTimeout(() => setIdle(true), IDLE_MS);
    }, []);

    useEffect(() => {
        wake();
        return () => clearTimeout(idleTimer.current);
    }, [wake, playing]);

    const hideChrome = idle && playing && !listOpen && !finished && !error;

    // Atajos: espacio/K pausa, flechas ±10 s, ↑↓ volumen, M silencio, F pantalla completa, N siguiente.
    useEffect(() => {
        const onKey = (event) => {
            if (event.target.closest?.('input, select, textarea')) return;
            const actions = {
                ' ': toggle, k: toggle, ArrowLeft: () => skip(-10), ArrowRight: () => skip(10),
                ArrowUp: () => changeVolume(Math.min(100, volume + 10)), ArrowDown: () => changeVolume(Math.max(0, volume - 10)),
                m: toggleMute, f: toggleFullscreen, n: () => hasNext && next(),
            };
            const action = actions[event.key];
            if (action) {
                event.preventDefault();
                wake();
                action();
            }
        };
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [toggle, skip, toggleMute, toggleFullscreen, next, hasNext, changeVolume, volume, wake]);

    const label = isSeries ? `T${source.season ?? 1}:E${source.number ?? index + 1} · ${source.title}` : null;
    const cover = source.thumbnail ?? item.poster ?? null;

    return (
        <div
            className={`nf-player${hideChrome ? ' is-idle' : ''}`}
            ref={rootRef}
            role="dialog"
            aria-modal="true"
            aria-label={`Reproduciendo ${item.title}`}
            onMouseMove={wake}
            onTouchStart={wake}
        >
            <div className="nf-player__top">
                <button type="button" className="nf-icon-btn" aria-label="Volver" ref={closeRef} onClick={onClose}>
                    <BackIcon />
                </button>
                <div className="nf-player__heading">
                    <strong>{item.title}</strong>
                    {label && <span>{label}</span>}
                </div>
            </div>

            <div className="nf-player__body">
                <div className="nf-player__stage">
                    {/* Carátula de carga. Con YouTube queda DETRÁS del iframe (que la tapa en cuanto aparece);
                        con el <video> propio va delante hasta que arranca. */}
                    {!finished && !error && (
                        <div className={`nf-player__cover${isYouTube ? '' : ' nf-player__cover--front'}${started ? ' is-hidden' : ''}`} aria-hidden="true">
                            {cover && <img src={cover} alt="" />}
                            <span className="nf-player__spinner" />
                        </div>
                    )}

                    {source.videoUrl ? (
                        <video
                            key={`${source.videoUrl}-${attempt}`}
                            ref={videoRef}
                            className="nf-player__frame"
                            src={source.videoUrl}
                            poster={item.poster ?? undefined}
                            playsInline
                            hidden={finished || Boolean(error)}
                            onError={() => { setPlaying(false); setError('mp4'); }}
                            onClick={toggle}
                            onDoubleClick={toggleFullscreen}
                            onPlay={() => setPlaying(true)}
                            onPlaying={() => setStarted(true)}
                            onPause={() => setPlaying(false)}
                            onEnded={next}
                            onLoadedMetadata={() => {
                                if (source.start) html5.seek(source.start);
                                // Si el navegador bloquea el autoplay con sonido, se reintenta en silencio.
                                videoRef.current?.play().catch(() => {
                                    if (!videoRef.current) return;
                                    videoRef.current.muted = true;
                                    onAutoMuted();
                                    videoRef.current.play().catch(() => {});
                                });
                            }}
                        />
                    ) : (
                        // "hidden" al terminar o si falla: el video se quita de la vista, no se tapa con nada.
                        <div key={attempt} className="nf-player__frame nf-player__frame--youtube" hidden={finished || Boolean(error)}><div ref={ytHostRef} /></div>
                    )}

                    {error && !finished && (
                        <div className="nf-player__error" role="alert">
                            {cover && <img className="nf-player__finished-bg" src={cover} alt="" />}
                            <p>{ERRORS[error] ?? ERRORS.default}</p>
                            <div className="nf-player__finished-actions">
                                <button type="button" className="nf-btn nf-btn--play" onClick={retry}>Reintentar</button>
                                {watchUrl && <a className="nf-btn nf-btn--ghost" href={watchUrl} target="_blank" rel="noopener noreferrer">Ver en {isYouTube ? 'YouTube' : 'Internet Archive'}</a>}
                                {hasNext && <button type="button" className="nf-btn nf-btn--ghost" onClick={() => { setError(null); next(); }}>Siguiente capítulo</button>}
                            </div>
                        </div>
                    )}

                    {finished && (
                        <div className="nf-player__finished">
                            {cover && <img className="nf-player__finished-bg" src={cover} alt="" />}
                            <p>{isSeries ? 'Terminaste la serie' : 'Fin'}</p>
                            <div className="nf-player__finished-actions">
                                <button type="button" className="nf-btn nf-btn--play" onClick={toggle}><PlayIcon /> Ver de nuevo</button>
                                <button type="button" className="nf-btn nf-btn--ghost" onClick={onClose}>Volver al catálogo</button>
                            </div>
                        </div>
                    )}
                </div>

                {listOpen && isSeries && (
                    <aside className="nf-player__episodes" aria-label="Capítulos">
                        <h2>Capítulos</h2>
                        <ol>
                            {sources.map((ep, i) => (
                                <li key={`${ep.videoId ?? ep.videoUrl}-${ep.start ?? 0}`}>
                                    <button
                                        type="button"
                                        className={i === index ? 'is-current' : ''}
                                        aria-current={i === index ? 'true' : undefined}
                                        onClick={() => { setFinished(false); setIndex(i); }}
                                    >
                                        <span className="nf-player__ep-num">{ep.number ?? i + 1}</span>
                                        <span className="nf-player__ep-title">{ep.title}</span>
                                        {ep.duration && <span className="nf-player__ep-time">{ep.duration} min</span>}
                                    </button>
                                </li>
                            ))}
                        </ol>
                    </aside>
                )}
            </div>

            <div className="nf-player__controls">
                <input
                    type="range"
                    className="nf-player__progress"
                    min="0"
                    max={length || 1}
                    step="1"
                    value={Math.min(time, length || 1)}
                    aria-label="Posición en el capítulo"
                    style={{ '--progress': `${length ? (time / length) * 100 : 0}%` }}
                    onChange={(e) => clampSeek(Number(e.target.value))}
                />
                <div className="nf-player__buttons">
                    <button type="button" className="nf-icon-btn" aria-label={playing ? 'Pausar' : 'Reproducir'} onClick={toggle}>
                        {playing ? <PauseIcon /> : <PlayIcon />}
                    </button>
                    <button type="button" className="nf-icon-btn" aria-label="Retroceder 10 segundos" onClick={() => skip(-10)}><Rewind /></button>
                    <button type="button" className="nf-icon-btn" aria-label="Adelantar 10 segundos" onClick={() => skip(10)}><Forward /></button>
                    <div className="nf-player__volume">
                        <button type="button" className="nf-icon-btn" aria-label={muted ? 'Activar sonido' : 'Silenciar'} onClick={toggleMute}><VolumeIcon muted={muted} /></button>
                        <input
                            type="range"
                            className="nf-player__volume-range"
                            min="0"
                            max="100"
                            step="5"
                            value={muted ? 0 : volume}
                            aria-label="Volumen"
                            style={{ '--progress': `${muted ? 0 : volume}%` }}
                            onChange={(e) => changeVolume(Number(e.target.value))}
                        />
                    </div>
                    {autoMuted && (
                        <button type="button" className="nf-player__unmute" onClick={toggleMute}>
                            <VolumeIcon muted={false} /> <span>Activar sonido</span>
                        </button>
                    )}
                    <span className="nf-player__clock">{clock(time)} / {clock(length)}</span>

                    <span className="nf-player__spacer" />

                    <button type="button" className="nf-player__rate" aria-label={`Velocidad ${rate}x. Cambiar velocidad`} onClick={cycleRate}>
                        {rate}x
                    </button>
                    {hasNext && (
                        <button type="button" className="nf-player__next" onClick={next}>
                            <NextIcon /> <span>Siguiente capítulo</span>
                        </button>
                    )}
                    {isSeries && (
                        <button type="button" className="nf-icon-btn" aria-label="Lista de capítulos" aria-pressed={listOpen} onClick={() => setListOpen((open) => !open)}>
                            <ListIcon />
                        </button>
                    )}
                    <button type="button" className="nf-icon-btn" aria-label={fullscreen ? 'Salir de pantalla completa' : 'Pantalla completa'} onClick={toggleFullscreen}>
                        <FullscreenIcon active={fullscreen} />
                    </button>
                </div>
                {item.channel && (
                    <p className="nf-player__credit">
                        Video de <a href={item.channelUrl ?? item.originalUrl} target="_blank" rel="noopener noreferrer">{item.channel}</a> en YouTube
                    </p>
                )}
            </div>
        </div>
    );
}
