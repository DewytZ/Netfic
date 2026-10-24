import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useOverlay } from './overlay.js';

/**
 * Reproductor de Netfic con controles propios.
 *
 * - Frutinovelas: reproductor oficial de YouTube (IFrame API) sin sus controles. Los controles de Netfic
 *   van DEBAJO del video, nunca encima: las políticas de YouTube no permiten tapar su reproductor.
 * - Clásicos de Internet Archive: <video> con el MP4, con los mismos controles.
 *
 * Un capítulo puede ser un tramo de un video ({ videoId, start, end } en segundos): así las
 * recopilaciones de una hora se ven como capítulos de ~8 minutos sin descargar ni cortar nada.
 */

const YT_HOST = 'https://www.youtube-nocookie.com';
const YT_ENDED = 0;
const YT_PLAYING = 1;

let youTubeApi;

// Carga la IFrame API de YouTube una sola vez.
function loadYouTubeApi() {
    youTubeApi ??= new Promise((resolve) => {
        if (window.YT?.Player) {
            resolve(window.YT);
            return;
        }
        const previous = window.onYouTubeIframeAPIReady;
        window.onYouTubeIframeAPIReady = () => {
            previous?.();
            resolve(window.YT);
        };
        const script = document.createElement('script');
        script.src = 'https://www.youtube.com/iframe_api';
        document.head.appendChild(script);
    });
    return youTubeApi;
}

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

function useYouTubeEngine(hostRef, source, { onPlayingChange, onEnded }) {
    const playerRef = useRef(null);
    const callbacks = useRef({ onPlayingChange, onEnded });
    callbacks.current = { onPlayingChange, onEnded };

    useEffect(() => {
        if (!source?.videoId) return undefined;
        let cancelled = false;
        const params = { videoId: source.videoId, startSeconds: source.start ?? 0, endSeconds: source.end ?? undefined };

        if (playerRef.current) {
            playerRef.current.loadVideoById(params);
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
                    playsinline: 1,
                    start: source.start ?? undefined,
                    end: source.end ?? undefined,
                    origin: window.location.origin,
                },
                events: {
                    onReady: (event) => event.target.playVideo(),
                    onStateChange: (event) => {
                        callbacks.current.onPlayingChange(event.data === YT_PLAYING);
                        if (event.data === YT_ENDED) callbacks.current.onEnded();
                    },
                },
            });
        });

        return () => { cancelled = true; };
    }, [hostRef, source?.videoId, source?.start, source?.end]);

    useEffect(() => () => playerRef.current?.destroy?.(), []);

    const p = () => playerRef.current;
    return useMemo(() => ({
        play: () => p()?.playVideo?.(),
        pause: () => p()?.pauseVideo?.(),
        seek: (seconds) => p()?.seekTo?.(seconds, true),
        time: () => p()?.getCurrentTime?.() ?? 0,
        duration: () => p()?.getDuration?.() ?? 0,
        setMuted: (muted) => (muted ? p()?.mute?.() : p()?.unMute?.()),
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
    const [muted, setMuted] = useState(false);
    const [time, setTime] = useState(0);
    const [length, setLength] = useState(0);
    const [listOpen, setListOpen] = useState(false);
    const [fullscreen, setFullscreen] = useState(false);
    const [finished, setFinished] = useState(false);

    const source = sources[index];
    const offset = source.start ?? 0;
    const hasNext = index < sources.length - 1;

    // El fin de un tramo se detecta dos veces (revisión del tiempo y evento "ended" de YouTube):
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

    const youTube = useYouTubeEngine(ytHostRef, source.videoUrl ? null : source, { onPlayingChange: setPlaying, onEnded: next });
    const html5 = useVideoEngine(videoRef);
    const engine = source.videoUrl ? html5 : youTube;

    // Avance: tiempo relativo al capítulo, y salto al siguiente al llegar al final del tramo.
    useEffect(() => {
        const timer = setInterval(() => {
            const absolute = engine.time();
            const end = source.end ?? engine.duration();
            setTime(Math.max(0, absolute - offset));
            setLength(Math.max(0, end - offset));
            // Recién cambiado de capítulo el tiempo aún es el del tramo anterior: se ignora un momento.
            const settled = Date.now() - switchedAt.current > 1500;
            if (settled && source.end && absolute >= source.end - 0.3 && absolute <= source.end + 2) next();
        }, 250);
        return () => clearInterval(timer);
    }, [engine, source, offset, next]);

    useEffect(() => { setTime(0); }, [index]);

    const toggle = useCallback(() => {
        if (finished) {
            setFinished(false);
            advancedFrom.current = -1;
            setIndex(0);
            engine.seek(sources[0].start ?? 0);
            engine.play();
            return;
        }
        playing ? engine.pause() : engine.play();
    }, [engine, playing, finished, sources]);

    const skip = useCallback((delta) => {
        engine.seek(Math.min(offset + length, Math.max(offset, offset + time + delta)));
    }, [engine, offset, length, time]);

    const toggleMute = useCallback(() => {
        engine.setMuted(!muted);
        setMuted(!muted);
    }, [engine, muted]);

    const toggleFullscreen = useCallback(() => {
        if (document.fullscreenElement) document.exitFullscreen();
        else rootRef.current?.requestFullscreen?.();
    }, []);

    useEffect(() => {
        const onChange = () => setFullscreen(Boolean(document.fullscreenElement));
        document.addEventListener('fullscreenchange', onChange);
        return () => document.removeEventListener('fullscreenchange', onChange);
    }, []);

    // Atajos: espacio/K pausa, flechas ±10 s, M silencio, F pantalla completa, N siguiente.
    useEffect(() => {
        const onKey = (event) => {
            if (event.target.closest?.('input, select, textarea')) return;
            const actions = {
                ' ': toggle, k: toggle, ArrowLeft: () => skip(-10), ArrowRight: () => skip(10),
                m: toggleMute, f: toggleFullscreen, n: () => hasNext && next(),
            };
            const action = actions[event.key];
            if (action) {
                event.preventDefault();
                action();
            }
        };
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [toggle, skip, toggleMute, toggleFullscreen, next, hasNext]);

    const label = isSeries ? `T${source.season ?? 1}:E${source.number ?? index + 1} · ${source.title}` : null;

    return (
        <div className="nf-player" ref={rootRef} role="dialog" aria-modal="true" aria-label={`Reproduciendo ${item.title}`}>
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
                    {source.videoUrl ? (
                        <video
                            key={source.videoUrl}
                            ref={videoRef}
                            className="nf-player__frame"
                            src={source.videoUrl}
                            poster={item.poster ?? undefined}
                            autoPlay
                            playsInline
                            onClick={toggle}
                            onPlay={() => setPlaying(true)}
                            onPause={() => setPlaying(false)}
                            onEnded={next}
                            onLoadedMetadata={() => source.start && html5.seek(source.start)}
                        />
                    ) : (
                        <div className="nf-player__frame"><div ref={ytHostRef} /></div>
                    )}
                    {finished && (
                        <div className="nf-player__finished">
                            <p>{isSeries ? 'Terminaste la serie' : 'Fin'}</p>
                            <button type="button" className="nf-btn nf-btn--play" onClick={toggle}><PlayIcon /> Ver de nuevo</button>
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
                    onChange={(e) => engine.seek(offset + Number(e.target.value))}
                />
                <div className="nf-player__buttons">
                    <button type="button" className="nf-icon-btn" aria-label={playing ? 'Pausar' : 'Reproducir'} onClick={toggle}>
                        {playing ? <PauseIcon /> : <PlayIcon />}
                    </button>
                    <button type="button" className="nf-icon-btn" aria-label="Retroceder 10 segundos" onClick={() => skip(-10)}><Rewind /></button>
                    <button type="button" className="nf-icon-btn" aria-label="Adelantar 10 segundos" onClick={() => skip(10)}><Forward /></button>
                    <button type="button" className="nf-icon-btn" aria-label={muted ? 'Activar sonido' : 'Silenciar'} onClick={toggleMute}><VolumeIcon muted={muted} /></button>
                    <span className="nf-player__clock">{clock(time)} / {clock(length)}</span>

                    <span className="nf-player__spacer" />

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
