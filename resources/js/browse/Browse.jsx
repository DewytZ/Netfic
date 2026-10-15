import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { buildCatalog, buildHomeRows } from './catalog.js';
import './Browse.css';

const MY_LIST_KEY = 'netfic:my-list';

const SECTIONS = [
    { id: 'home', label: 'Inicio' },
    { id: 'series', label: 'Series' },
    { id: 'movies', label: 'Películas' },
    { id: 'my-list', label: 'Mi lista' },
];

const art = (item, angle = 160) => ({ background: `linear-gradient(${angle}deg, ${item.colors[0]}, ${item.colors[1]})` });

/* ---------- Íconos ---------- */

const Icon = ({ children, size = 20 }) => (
    <svg viewBox="0 0 24 24" width={size} height={size} fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
        {children}
    </svg>
);
const PlayIcon = ({ size }) => <svg viewBox="0 0 24 24" width={size ?? 20} height={size ?? 20} fill="currentColor" aria-hidden="true"><path d="M7 4.5v15a1 1 0 0 0 1.5.86l12.5-7.5a1 1 0 0 0 0-1.72L8.5 3.64A1 1 0 0 0 7 4.5z" /></svg>;
const PauseIcon = () => <svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor" aria-hidden="true"><rect x="6" y="4" width="4" height="16" rx="1" /><rect x="14" y="4" width="4" height="16" rx="1" /></svg>;
const InfoIcon = () => <Icon><circle cx="12" cy="12" r="10" /><path d="M12 16v-4M12 8h.01" /></Icon>;
const PlusIcon = () => <Icon><path d="M12 5v14M5 12h14" /></Icon>;
const CheckIcon = () => <Icon><path d="M20 6 9 17l-5-5" /></Icon>;
const CloseIcon = () => <Icon><path d="M18 6 6 18M6 6l12 12" /></Icon>;
const SearchIcon = () => <Icon><circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" /></Icon>;
const ChevronIcon = ({ dir }) => <Icon size={28}>{dir === 'left' ? <path d="m15 18-6-6 6-6" /> : <path d="m9 18 6-6-6-6" />}</Icon>;
const BackIcon = () => <Icon size={28}><path d="M19 12H5M12 19l-7-7 7-7" /></Icon>;
const LogoutIcon = () => <Icon size={18}><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" /><path d="M16 17l5-5-5-5" /><path d="M21 12H9" /></Icon>;

/* ---------- "Mi lista" guardada en el navegador ---------- */

function useMyList() {
    const [ids, setIds] = useState(() => {
        try {
            return JSON.parse(localStorage.getItem(MY_LIST_KEY)) ?? [];
        } catch {
            return [];
        }
    });

    useEffect(() => {
        try {
            localStorage.setItem(MY_LIST_KEY, JSON.stringify(ids));
        } catch {
            // Sin almacenamiento (modo privado): la lista dura mientras la página esté abierta.
        }
    }, [ids]);

    const toggle = useCallback((id) => {
        setIds((current) => (current.includes(id) ? current.filter((x) => x !== id) : [id, ...current]));
    }, []);

    return { ids, has: (id) => ids.includes(id), toggle };
}

/* ---------- Barra superior ---------- */

function Navbar({ user, csrfToken, section, onSection, query, onQuery }) {
    const [scrolled, setScrolled] = useState(false);
    const [searchOpen, setSearchOpen] = useState(Boolean(query));
    const [menuOpen, setMenuOpen] = useState(false);
    const searchRef = useRef(null);
    const menuRef = useRef(null);

    useEffect(() => {
        const onScroll = () => setScrolled(window.scrollY > 20);
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    useEffect(() => {
        if (searchOpen) searchRef.current?.focus();
    }, [searchOpen]);

    useEffect(() => {
        if (!menuOpen) return undefined;
        const close = (event) => {
            if (event.type === 'keydown' ? event.key === 'Escape' : !menuRef.current?.contains(event.target)) {
                setMenuOpen(false);
            }
        };
        document.addEventListener('mousedown', close);
        document.addEventListener('keydown', close);
        return () => {
            document.removeEventListener('mousedown', close);
            document.removeEventListener('keydown', close);
        };
    }, [menuOpen]);

    const initial = user.name.trim().charAt(0).toUpperCase();

    return (
        <header className={`nf-nav${scrolled ? ' nf-nav--solid' : ''}`}>
            <a href="/dashboard" className="nf-logo" onClick={(e) => { e.preventDefault(); onQuery(''); onSection('home'); }}>
                <span className="nf-logo__dot" />
                Netfic
            </a>

            <nav className="nf-nav__links" aria-label="Secciones">
                {SECTIONS.map((s) => (
                    <button
                        key={s.id}
                        type="button"
                        className={`nf-nav__link${section === s.id && !query ? ' is-active' : ''}`}
                        aria-current={section === s.id && !query ? 'page' : undefined}
                        onClick={() => { onQuery(''); onSection(s.id); }}
                    >
                        {s.label}
                    </button>
                ))}
            </nav>

            <div className="nf-nav__actions">
                <div className={`nf-search${searchOpen ? ' is-open' : ''}`}>
                    <button type="button" className="nf-icon-btn" aria-label="Buscar" onClick={() => setSearchOpen(true)}>
                        <SearchIcon />
                    </button>
                    <input
                        ref={searchRef}
                        type="search"
                        placeholder="Títulos, géneros"
                        aria-label="Buscar títulos o géneros"
                        value={query}
                        onChange={(e) => onQuery(e.target.value)}
                        onBlur={() => !query && setSearchOpen(false)}
                        onKeyDown={(e) => { if (e.key === 'Escape') { onQuery(''); setSearchOpen(false); } }}
                    />
                </div>

                <div className="nf-profile" ref={menuRef}>
                    <button
                        type="button"
                        className="nf-avatar"
                        aria-label={`Menú de ${user.name}`}
                        aria-expanded={menuOpen}
                        aria-haspopup="menu"
                        onClick={() => setMenuOpen((open) => !open)}
                    >
                        {initial}
                    </button>

                    {menuOpen && (
                        <div className="nf-menu" role="menu">
                            <div className="nf-menu__user">
                                <span className="nf-avatar nf-avatar--small" aria-hidden="true">{initial}</span>
                                <div>
                                    <strong>{user.name}</strong>
                                    <span>{user.email}</span>
                                </div>
                            </div>
                            <form method="POST" action="/logout">
                                <input type="hidden" name="_token" value={csrfToken} />
                                <button type="submit" className="nf-menu__logout" role="menuitem">
                                    <LogoutIcon />
                                    Cerrar sesión
                                </button>
                            </form>
                        </div>
                    )}
                </div>
            </div>
        </header>
    );
}

/* ---------- Portada destacada ---------- */

function Hero({ item, onPlay, onInfo }) {
    return (
        <section className="nf-hero" style={art(item, 120)} aria-label="Destacado">
            <div className="nf-hero__glow" />
            <div className="nf-hero__content">
                <span className="nf-hero__badge">
                    <span className="nf-logo__dot" /> {item.type === 'Serie' ? 'SERIE' : 'PELÍCULA'}
                </span>
                <h1 className="nf-hero__title">{item.title}</h1>
                <div className="nf-meta">
                    <span className="nf-meta__match">{item.match}% para ti</span>
                    <span>{item.year}</span>
                    <span className="nf-meta__rating">{item.maturity}</span>
                    <span>{item.duration}</span>
                </div>
                <p className="nf-hero__desc">{item.description}</p>
                <div className="nf-hero__buttons">
                    <button type="button" className="nf-btn nf-btn--play" onClick={() => onPlay(item)}>
                        <PlayIcon size={22} /> Reproducir
                    </button>
                    <button type="button" className="nf-btn nf-btn--ghost" onClick={() => onInfo(item)}>
                        <InfoIcon /> Más información
                    </button>
                </div>
            </div>
        </section>
    );
}

/* ---------- Tarjetas y filas ---------- */

function Card({ item, rank, progress, onOpen }) {
    return (
        <button type="button" className={`nf-card${rank ? ' nf-card--ranked' : ''}`} onClick={() => onOpen(item)} aria-label={`${item.title}, ${item.genre}`}>
            {rank && <span className="nf-card__rank" aria-hidden="true">{rank}</span>}
            <span className="nf-card__art" style={art(item)}>
                <span className="nf-card__type">{item.type === 'Serie' ? 'S' : 'P'}</span>
                <span className="nf-card__title">{item.title}</span>
                <span className="nf-card__hover">
                    <span className="nf-card__play"><PlayIcon size={16} /></span>
                    <span className="nf-card__info">
                        <span className="nf-meta__match">{item.match}%</span>
                        <span className="nf-meta__rating">{item.maturity}</span>
                        <span>{item.genre}</span>
                    </span>
                </span>
                {progress !== undefined && (
                    <span className="nf-card__progress" aria-label={`Visto ${progress}%`}>
                        <span style={{ width: `${progress}%` }} />
                    </span>
                )}
            </span>
        </button>
    );
}

function Row({ row, onOpen }) {
    const trackRef = useRef(null);
    const [edges, setEdges] = useState({ start: true, end: false });

    const updateEdges = useCallback(() => {
        const el = trackRef.current;
        if (!el) return;
        setEdges({ start: el.scrollLeft < 8, end: el.scrollLeft + el.clientWidth >= el.scrollWidth - 8 });
    }, []);

    useEffect(() => {
        updateEdges();
        window.addEventListener('resize', updateEdges);
        return () => window.removeEventListener('resize', updateEdges);
    }, [updateEdges]);

    const scroll = (dir) => {
        const el = trackRef.current;
        el.scrollBy({ left: dir * el.clientWidth * 0.85, behavior: 'smooth' });
    };

    if (!row.items.length) return null;

    return (
        <section className="nf-row" aria-label={row.title}>
            <h2 className="nf-row__title">{row.title}</h2>
            <div className="nf-row__wrap">
                {!edges.start && (
                    <button type="button" className="nf-row__arrow nf-row__arrow--left" aria-label="Anteriores" onClick={() => scroll(-1)}>
                        <ChevronIcon dir="left" />
                    </button>
                )}
                <div className="nf-row__track" ref={trackRef} onScroll={updateEdges}>
                    {row.items.map((item, i) => (
                        <Card
                            key={item.id}
                            item={item}
                            rank={row.ranked ? i + 1 : undefined}
                            progress={row.progress?.[i]}
                            onOpen={onOpen}
                        />
                    ))}
                </div>
                {!edges.end && (
                    <button type="button" className="nf-row__arrow nf-row__arrow--right" aria-label="Siguientes" onClick={() => scroll(1)}>
                        <ChevronIcon dir="right" />
                    </button>
                )}
            </div>
        </section>
    );
}

function Grid({ title, items, empty, onOpen }) {
    return (
        <section className="nf-grid-section">
            <h2 className="nf-grid-section__title">{title}</h2>
            {items.length ? (
                <div className="nf-grid">
                    {items.map((item) => <Card key={item.id} item={item} onOpen={onOpen} />)}
                </div>
            ) : (
                <p className="nf-empty">{empty}</p>
            )}
        </section>
    );
}

/* ---------- Capas: ficha de detalle y reproductor ---------- */

function useOverlay(onClose) {
    const closeRef = useRef(null);

    useEffect(() => {
        const previous = document.activeElement;
        const onKey = (event) => event.key === 'Escape' && onClose();
        document.body.classList.add('nf-lock');
        document.addEventListener('keydown', onKey);
        closeRef.current?.focus();

        return () => {
            document.body.classList.remove('nf-lock');
            document.removeEventListener('keydown', onKey);
            previous?.focus?.();
        };
    }, [onClose]);

    return closeRef;
}

function DetailModal({ item, catalog, inList, onToggleList, onPlay, onOpen, onClose }) {
    const closeRef = useOverlay(onClose);
    const similar = catalog.filter((other) => other.genre === item.genre && other.id !== item.id)
        .concat(catalog.filter((other) => other.type === item.type && other.genre !== item.genre))
        .slice(0, 6);

    return (
        <div className="nf-modal" role="dialog" aria-modal="true" aria-labelledby="nf-modal-title" onMouseDown={(e) => e.target === e.currentTarget && onClose()}>
            <div className="nf-modal__panel">
                <button type="button" className="nf-modal__close" aria-label="Cerrar" ref={closeRef} onClick={onClose}>
                    <CloseIcon />
                </button>

                <div className="nf-modal__art" style={art(item, 120)}>
                    <div className="nf-modal__art-fade" />
                    <div className="nf-modal__heading">
                        <h2 id="nf-modal-title">{item.title}</h2>
                        <div className="nf-hero__buttons">
                            <button type="button" className="nf-btn nf-btn--play" onClick={() => onPlay(item)}>
                                <PlayIcon size={22} /> Reproducir
                            </button>
                            <button
                                type="button"
                                className="nf-round-btn"
                                aria-pressed={inList}
                                aria-label={inList ? 'Quitar de Mi lista' : 'Agregar a Mi lista'}
                                title={inList ? 'Quitar de Mi lista' : 'Agregar a Mi lista'}
                                onClick={() => onToggleList(item.id)}
                            >
                                {inList ? <CheckIcon /> : <PlusIcon />}
                            </button>
                        </div>
                    </div>
                </div>

                <div className="nf-modal__body">
                    <div className="nf-modal__info">
                        <div className="nf-meta">
                            <span className="nf-meta__match">{item.match}% para ti</span>
                            <span>{item.year}</span>
                            <span className="nf-meta__rating">{item.maturity}</span>
                            <span>{item.duration}</span>
                        </div>
                        <p>{item.description}</p>
                    </div>
                    <dl className="nf-modal__facts">
                        <div><dt>Género:</dt><dd>{item.genre}</dd></div>
                        <div><dt>Tipo:</dt><dd>{item.type}</dd></div>
                    </dl>
                </div>

                <div className="nf-modal__similar">
                    <h3>Títulos similares</h3>
                    <div className="nf-grid nf-grid--compact">
                        {similar.map((other) => <Card key={other.id} item={other} onOpen={onOpen} />)}
                    </div>
                </div>
            </div>
        </div>
    );
}

const formatTime = (seconds) => `${Math.floor(seconds / 60)}:${String(Math.floor(seconds % 60)).padStart(2, '0')}`;
const SIMULATED_LENGTH = 90; // El "capítulo" dura 90 s para que la simulación se vea avanzar.

function Player({ item, onClose }) {
    const closeRef = useOverlay(onClose);
    const [loading, setLoading] = useState(true);
    const [playing, setPlaying] = useState(true);
    const [time, setTime] = useState(0);

    useEffect(() => {
        const timer = setTimeout(() => setLoading(false), 1500);
        return () => clearTimeout(timer);
    }, []);

    useEffect(() => {
        if (loading || !playing) return undefined;
        const timer = setInterval(() => setTime((t) => Math.min(t + 0.25, SIMULATED_LENGTH)), 250);
        return () => clearInterval(timer);
    }, [loading, playing]);

    useEffect(() => {
        if (time >= SIMULATED_LENGTH) setPlaying(false);
    }, [time]);

    useEffect(() => {
        const onKey = (event) => {
            if (event.key === ' ' && !loading) {
                event.preventDefault();
                setPlaying((p) => !p);
            }
        };
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [loading]);

    const restart = () => { setTime(0); setPlaying(true); };

    return (
        <div className="nf-player" role="dialog" aria-modal="true" aria-label={`Reproduciendo ${item.title}`}>
            <div className={`nf-player__screen${playing && !loading ? ' is-playing' : ''}`} style={art(item, 135)} />

            <div className="nf-player__top">
                <button type="button" className="nf-icon-btn" aria-label="Volver" ref={closeRef} onClick={onClose}>
                    <BackIcon />
                </button>
                <span className="nf-player__now">Estás viendo <strong>{item.title}</strong></span>
            </div>

            {loading ? (
                <div className="nf-player__center">
                    <span className="nf-spinner" aria-label="Cargando" />
                </div>
            ) : (
                <button
                    type="button"
                    className="nf-player__center nf-player__toggle"
                    aria-label={playing ? 'Pausar' : 'Reproducir'}
                    onClick={() => (time >= SIMULATED_LENGTH ? restart() : setPlaying((p) => !p))}
                >
                    {!playing && <span className="nf-player__big">{time >= SIMULATED_LENGTH ? '↻' : <PlayIcon size={44} />}</span>}
                </button>
            )}

            <div className="nf-player__bottom">
                <div className="nf-player__bar">
                    <input
                        type="range"
                        min="0"
                        max={SIMULATED_LENGTH}
                        step="1"
                        value={time}
                        aria-label="Posición"
                        style={{ '--progress': `${(time / SIMULATED_LENGTH) * 100}%` }}
                        onChange={(e) => setTime(Number(e.target.value))}
                    />
                    <span className="nf-player__time">{formatTime(SIMULATED_LENGTH - time)}</span>
                </div>
                <div className="nf-player__controls">
                    <button type="button" className="nf-icon-btn" aria-label={playing ? 'Pausar' : 'Reproducir'} disabled={loading} onClick={() => (time >= SIMULATED_LENGTH ? restart() : setPlaying((p) => !p))}>
                        {playing ? <PauseIcon /> : <PlayIcon size={28} />}
                    </button>
                    <span className="nf-player__label">{item.title} · {item.type === 'Serie' ? 'T1:E1 "Piloto"' : item.genre}</span>
                    <span className="nf-player__note">Simulación: no hay video real</span>
                </div>
            </div>
        </div>
    );
}

/* ---------- Página ---------- */

export default function Browse({ user, csrfToken, titles }) {
    const [section, setSection] = useState('home');
    const [query, setQuery] = useState('');
    const [detail, setDetail] = useState(null);
    const [playing, setPlaying] = useState(null);
    const myList = useMyList();

    // Todos los títulos de la base de datos (películas y series).
    const catalog = useMemo(() => buildCatalog(titles), [titles]);
    const homeRows = useMemo(() => buildHomeRows(catalog), [catalog]);

    // Destacado al azar en cada visita, como la portada de Netflix.
    const featured = useMemo(() => {
        const options = catalog.filter((item) => item.year >= 2024);
        return options[Math.floor(Math.random() * options.length)];
    }, [catalog]);

    useEffect(() => {
        window.scrollTo({ top: 0 });
    }, [section]);

    const closeDetail = useCallback(() => setDetail(null), []);
    const closePlayer = useCallback(() => setPlaying(null), []);
    const play = useCallback((item) => { setDetail(null); setPlaying(item); }, []);

    const listItems = myList.ids.map((id) => catalog.find((item) => item.id === id)).filter(Boolean);
    const term = query.trim().toLowerCase();
    const results = term
        ? catalog.filter((item) => `${item.title} ${item.genre} ${item.type}`.toLowerCase().includes(term))
        : [];

    let content;
    if (term) {
        content = <Grid title={`Resultados para "${query.trim()}"`} items={results} empty="No encontramos títulos con esa búsqueda." onOpen={setDetail} />;
    } else if (section === 'my-list') {
        content = <Grid title="Mi lista" items={listItems} empty='Aún no agregas títulos. Abre uno y usa el botón "+" para guardarlo aquí.' onOpen={setDetail} />;
    } else {
        const type = { series: 'Serie', movies: 'Película' }[section];
        const rows = [
            ...(listItems.length && !type ? [{ id: 'my-list', title: 'Mi lista', items: listItems }] : []),
            ...homeRows,
        ]
            .map((row) => {
                if (!type) return row;
                const keep = row.items.map((item, i) => [item, row.progress?.[i]]).filter(([item]) => item.type === type);
                return { ...row, items: keep.map(([item]) => item), progress: row.progress && keep.map(([, p]) => p) };
            })
            .filter((row) => row.items.length >= 2);

        const heroItem = type && featured?.type !== type ? catalog.find((item) => item.type === type && item.year >= 2024) : featured;

        content = (
            <>
                {heroItem && <Hero item={heroItem} onPlay={play} onInfo={setDetail} />}
                <div className="nf-rows">
                    {rows.map((row) => <Row key={`${section}-${row.id}`} row={row} onOpen={setDetail} />)}
                </div>
            </>
        );
    }

    return (
        <div className="nf-app">
            <Navbar user={user} csrfToken={csrfToken} section={section} onSection={setSection} query={query} onQuery={setQuery} />
            <main className={`nf-main${term || section === 'my-list' ? ' nf-main--plain' : ''}`}>{content}</main>
            <footer className="nf-footer">Netfic · Proyecto escolar. Catálogo y reproducción simulados.</footer>

            {detail && (
                <DetailModal
                    key={detail.id}
                    item={detail}
                    catalog={catalog}
                    inList={myList.has(detail.id)}
                    onToggleList={myList.toggle}
                    onPlay={play}
                    onOpen={setDetail}
                    onClose={closeDetail}
                />
            )}
            {playing && <Player item={playing} onClose={closePlayer} />}
        </div>
    );
}
