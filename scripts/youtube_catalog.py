"""
Extrae METADATOS (nunca descarga video) de YouTube con yt-dlp para el catálogo de frutinovelas.

Los videos se reproducen después con el reproductor oficial de YouTube (iframe), así que
solo se aceptan los que el canal permite insertar y sin restricción de edad.

Uso (normalmente lo llama `php artisan content:import-youtube`):
    python scripts/youtube_catalog.py --search "frutinovelas completas" --max 10
    python scripts/youtube_catalog.py --series "frutinovela capitulos" --max 5
    python scripts/youtube_catalog.py --playlist "https://www.youtube.com/playlist?list=..."
    python scripts/youtube_catalog.py --videos ID1 ID2 ...
    python scripts/youtube_catalog.py --search "frutinovelas" --out frutinovelas.json

Requiere: pip install yt-dlp
"""

import argparse
import json
import re
import sys

import yt_dlp

# Títulos en español: sin esto YouTube devuelve títulos traducidos automáticamente al inglés.
BASE_OPTS = {
    "quiet": True,
    "no_warnings": True,
    "skip_download": True,
    "extractor_args": {"youtube": {"lang": ["es"]}},
    "http_headers": {"Accept-Language": "es-419,es;q=0.9"},
}

# Filtro de YouTube para que la búsqueda devuelva solo listas de reproducción.
PLAYLISTS_FILTER = "EgIQAw%3D%3D"

# Una serie se acepta si al menos este porcentaje de sus videos es del dueño de la lista
# (así evitamos listas de usuarios que juntan videos de otros canales).
MIN_OWN_VIDEOS = 0.7

# Una serie real numera sus capítulos ("EP. 1", "Capítulo 2", "Parte 3") o lo dice en el nombre de la lista.
EPISODE_MARK = re.compile(r"\b(ep|episodio|cap[ií]tulo|cap|parte|part)\.?\s*\d+|#\d+", re.IGNORECASE)
SERIES_NAME = re.compile(r"temporada|episodios|cap[ií]tulos en orden|season", re.IGNORECASE)
# Recopilaciones de historias sueltas: no son una serie.
COMPILATION_NAME = re.compile(r"marat[oó]n|compilaci|\bmix\b|\+?\s*\d+\s*horas?\b", re.IGNORECASE)


def embed_url(video_id):
    return f"https://www.youtube-nocookie.com/embed/{video_id}"


def thumbnail(video_id):
    # hqdefault existe siempre; maxresdefault no todos los videos lo tienen.
    return f"https://i.ytimg.com/vi/{video_id}/hqdefault.jpg"


def skip_reason(info, check_language=True):
    if info.get("live_status") in ("is_live", "is_upcoming", "post_live"):
        return "transmisión en vivo"
    if (info.get("age_limit") or 0) >= 18:
        return "restringido por edad en YouTube"
    if info.get("playable_in_embed") is False:
        return "el canal no permite insertarlo"
    if info.get("availability") not in (None, "public", "unlisted"):
        return f"no es público ({info.get('availability')})"
    # En una serie el idioma se revisa para toda la lista (series_skip_reason): YouTube a veces
    # marca mal un capítulo suelto y descartarlo dejaría un hueco en la historia.
    language = info.get("language")
    if check_language and language and not language.startswith("es"):
        return f"no está en español ({language})"
    return None


def video_details(ydl, video_id, check_language=True):
    """Info completa de un video (incluye playable_in_embed y age_limit)."""
    info = ydl.extract_info(f"https://www.youtube.com/watch?v={video_id}", download=False)
    return {
        "id": info["id"],
        "title": info.get("title"),
        "description": info.get("description"),
        "duration": info.get("duration"),  # segundos
        "channel": info.get("channel") or info.get("uploader"),
        "channel_url": info.get("channel_url") or info.get("uploader_url"),
        "url": info.get("webpage_url") or f"https://www.youtube.com/watch?v={info['id']}",
        "embed_url": embed_url(info["id"]),
        "thumbnail": thumbnail(info["id"]),
        "tags": info.get("tags") or [],
        "categories": info.get("categories") or [],
        "upload_date": info.get("upload_date"),  # AAAAMMDD
        "language": info.get("language"),
        "channel_id": info.get("channel_id"),
        # Escenas marcadas por el canal; Laravel las agrupa en capítulos de ~8 minutos.
        "chapters": [
            {"title": c.get("title"), "start": int(c["start_time"]), "end": int(c["end_time"])}
            for c in (info.get("chapters") or [])
            if c.get("start_time") is not None and c.get("end_time") is not None
        ],
        "_skip": skip_reason(info, check_language),
    }


def collect(entries, ydl, max_results=None, check_language=True):
    items, skipped = [], []
    for entry in entries:
        if max_results and len(items) >= max_results:
            break
        video_id = entry.get("id")
        if not video_id:
            continue
        try:
            details = video_details(ydl, video_id, check_language)
        except yt_dlp.utils.DownloadError as error:
            skipped.append({"id": video_id, "title": entry.get("title"), "reason": str(error).splitlines()[0]})
            continue
        reason = details.pop("_skip")
        if reason:
            skipped.append({"id": video_id, "title": details["title"], "reason": reason})
        else:
            items.append(details)
    return items, skipped


def search(query, max_results):
    # Pedimos el doble para compensar los que se descartan.
    with yt_dlp.YoutubeDL({**BASE_OPTS, "extract_flat": True}) as flat:
        result = flat.extract_info(f"ytsearch{max_results * 2}:{query}", download=False)
    with yt_dlp.YoutubeDL(BASE_OPTS) as ydl:
        items, skipped = collect(result.get("entries") or [], ydl, max_results)
    return {"playlist": None, "items": items, "skipped": skipped}


def playlist(url, max_episodes=40):
    with yt_dlp.YoutubeDL({**BASE_OPTS, "extract_flat": True, "playlistend": max_episodes}) as flat:
        result = flat.extract_info(url, download=False)
    with yt_dlp.YoutubeDL(BASE_OPTS) as ydl:
        items, skipped = collect(result.get("entries") or [], ydl, check_language=False)
    meta = {
        "id": result.get("id"),
        "title": result.get("title"),
        "description": result.get("description"),
        "channel": result.get("channel") or result.get("uploader"),
        "channel_url": result.get("channel_url") or result.get("uploader_url"),
        "channel_id": result.get("channel_id") or result.get("uploader_id"),
        "url": result.get("webpage_url") or url,
    }
    return {"playlist": meta, "items": items, "skipped": skipped}


def series_skip_reason(data):
    """Por qué una lista no sirve como serie (o None si sirve)."""
    items, owner = data["items"], data["playlist"].get("channel_id")
    name = data["playlist"].get("title") or ""
    if len(items) < 3:
        return f"solo {len(items)} capítulos utilizables"
    languages = [item["language"] for item in items if item.get("language")]
    if languages and sum(1 for lang in languages if lang.startswith("es")) / len(languages) < 0.5:
        return "la serie no está en español"
    if COMPILATION_NAME.search(name):
        return "es una recopilación, no una serie"
    numbered = sum(1 for item in items if EPISODE_MARK.search(item.get("title") or ""))
    if numbered / len(items) < 0.5 and not SERIES_NAME.search(name):
        return "los videos no son capítulos numerados de una misma historia"
    if owner:
        own = sum(1 for item in items if item.get("channel_id") == owner)
        if own / len(items) < MIN_OWN_VIDEOS:
            return "la lista junta videos de otros canales"
    return None


def series(query, max_series, max_episodes):
    """Busca listas de reproducción y devuelve las que son series reales del propio canal."""
    url = f"https://www.youtube.com/results?search_query={query.replace(' ', '+')}&sp={PLAYLISTS_FILTER}"
    with yt_dlp.YoutubeDL({**BASE_OPTS, "extract_flat": True, "playlistend": max_series * 3}) as flat:
        found = flat.extract_info(url, download=False).get("entries") or []

    accepted, rejected = [], []
    for entry in found:
        if len(accepted) >= max_series:
            break
        if not entry.get("id"):
            continue
        if COMPILATION_NAME.search(entry.get("title") or ""):
            rejected.append({"id": entry["id"], "title": entry.get("title"), "reason": "es una recopilación, no una serie"})
            continue
        data = playlist(f"https://www.youtube.com/playlist?list={entry['id']}", max_episodes)
        reason = series_skip_reason(data)
        if reason:
            rejected.append({"id": entry["id"], "title": data["playlist"].get("title"), "reason": reason})
        else:
            accepted.append(data)
    return {"series": accepted, "skipped": rejected, "playlist": None, "items": []}


def videos(ids):
    with yt_dlp.YoutubeDL(BASE_OPTS) as ydl:
        items, skipped = collect([{"id": video_id} for video_id in ids], ydl)
    return {"playlist": None, "items": items, "skipped": skipped}


def main():
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    source = parser.add_mutually_exclusive_group(required=True)
    source.add_argument("--search", help="Búsqueda en YouTube")
    source.add_argument("--playlist", help="URL de una lista: se importa como una novela con capítulos")
    source.add_argument("--series", help="Busca listas de reproducción que sean series reales del canal")
    source.add_argument("--videos", nargs="+", help="IDs de videos concretos (para actualizar los ya importados)")
    parser.add_argument("--max", type=int, default=10, help="Máximo de videos (--search) o de series (--series)")
    parser.add_argument("--max-episodes", type=int, default=40, help="Máximo de capítulos por serie")
    parser.add_argument("--out", help="Guardar en un archivo en vez de imprimir")
    args = parser.parse_args()

    if args.search:
        data = search(args.search, args.max)
    elif args.series:
        data = series(args.series, args.max, args.max_episodes)
    elif args.videos:
        data = videos(args.videos)
    else:
        data = playlist(args.playlist, args.max_episodes)
    payload = json.dumps(data, indent=2, ensure_ascii=False)

    if args.out:
        with open(args.out, "w", encoding="utf-8") as file:
            file.write(payload)
        total = len(data["items"]) + sum(len(s["items"]) for s in data.get("series", []))
        print(f"Exportados {total} videos a {args.out} ({len(data['skipped'])} descartados)", file=sys.stderr)
    else:
        sys.stdout.reconfigure(encoding="utf-8")
        print(payload)


if __name__ == "__main__":
    main()
