// resources/js/admin/ContentForm.jsx
import { useState } from "react";
import { csrfToken } from "./csrf.js";

// Mismos valores que App\Models\Content y App\Services\GenreCategorizer::GENRES.
export const TYPES = {
  pelicula: "Película",
  serie: "Serie",
  novela: "Novela",
  corto: "Corto",
  documental: "Documental",
};
export const SOURCES = {
  internet_archive: "Internet Archive",
  frutinovela: "Frutinovela",
  ia: "IA",
};
const LICENSES = {
  "dominio-publico": "Dominio público",
  cc0: "CC0",
  "cc-by": "CC BY",
  "cc-by-sa": "CC BY-SA",
  "original-ia": "Original IA",
  "youtube-embed": "YouTube (reproductor insertado)",
  desconocida: "Desconocida (revisar antes de publicar)",
};
const GENRES = [
  "Acción", "Animación", "Aventura", "Bélico", "Ciencia ficción", "Cine negro", "Comedia",
  "Crimen", "Documental", "Drama", "Familia", "Fantasía", "Histórico", "Misterio", "Musical",
  "Romance", "Terror", "Thriller", "Western",
];
// "18+" nunca se muestra al público (Content::scopePublished).
const MATURITY_OPTIONS = ["TP", "7+", "13+", "16+", "18+"];

const EMPTY_FORM = {
  title: "",
  type: "pelicula",
  source: "internet_archive",
  license: "dominio-publico",
  year: "",
  duration: "",
  maturity: "13+",
  genres: [],
  tags: "",        // texto separado por comas
  synopsis: "",
  poster_url: "",
  video_url: "",
  embed_url: "",
  published: false,
};

// Documento de MongoDB -> valores del formulario
function toFormState(content) {
  return {
    ...EMPTY_FORM,
    ...Object.fromEntries(
      Object.keys(EMPTY_FORM).map((key) => [key, content[key] ?? EMPTY_FORM[key]])
    ),
    year: content.year ? String(content.year) : "",
    duration: content.duration ? String(content.duration) : "",
    genres: Array.isArray(content.genres) ? content.genres : [],
    tags: Array.isArray(content.tags) ? content.tags.join(", ") : "",
    published: Boolean(content.published),
  };
}

// Valores del formulario -> cuerpo que espera ContentRequest. Los campos vacíos van como null.
// Los capítulos (episodes) no se tocan: al no enviarse, el documento conserva los que tenga.
function buildPayload(form) {
  const text = (value) => value.trim() || null;
  const tags = form.tags.split(",").map((tag) => tag.trim()).filter(Boolean);

  return {
    title: form.title.trim(),
    type: form.type,
    source: form.source,
    license: form.license,
    year: form.year ? Number(form.year) : null,
    duration: form.duration ? Number(form.duration) : null,
    maturity: form.maturity,
    genres: form.genres,
    tags: tags.length > 0 ? tags : null,
    synopsis: text(form.synopsis),
    poster_url: text(form.poster_url),
    video_url: text(form.video_url),
    embed_url: text(form.embed_url),
    published: form.published,
  };
}

// Laravel responde { message, errors: { campo: [mensajes] } } cuando falla la validación.
export async function readErrors(response) {
  if (response.status === 401) return ["Tu sesión expiró. Vuelve a iniciar sesión."];
  if (response.status === 419) return ["La página caducó. Recárgala e intenta de nuevo."];

  const data = await response.json().catch(() => ({}));
  if (data.errors) return Object.values(data.errors).flat();

  return [data.message || `Error ${response.status}`];
}

/**
 * Formulario para crear contenido multimedia o, si recibe `initialData`, para editarlo.
 * @param {{initialData?: object, onSuccess: () => void}} props
 */
export default function ContentForm({ initialData, onSuccess }) {
  const isEditing = Boolean(initialData);

  const [form, setForm] = useState(() => (isEditing ? toFormState(initialData) : EMPTY_FORM));
  const [loading, setLoading] = useState(false);
  const [errors, setErrors] = useState([]);
  const [successMsg, setSuccessMsg] = useState(null);

  const handleChange = (e) => {
    const { name, value, type, checked } = e.target;
    setForm((prev) => ({ ...prev, [name]: type === "checkbox" ? checked : value }));
  };

  const toggleGenre = (genre) => {
    setForm((prev) => ({
      ...prev,
      genres: prev.genres.includes(genre)
        ? prev.genres.filter((g) => g !== genre)
        : [...prev.genres, genre],
    }));
  };

  // POST para crear, PUT para editar
  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setErrors([]);
    setSuccessMsg(null);

    const url = isEditing ? `/admin-api/contents/${initialData.id}` : "/admin-api/contents";

    try {
      const response = await fetch(url, {
        method: isEditing ? "PUT" : "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-CSRF-TOKEN": csrfToken(),
        },
        body: JSON.stringify(buildPayload(form)),
      });

      if (!response.ok) {
        setErrors(await readErrors(response));
        return;
      }

      if (isEditing) {
        onSuccess?.();
        return;
      }

      setSuccessMsg("✅ Contenido creado correctamente");
      setForm(EMPTY_FORM);
      onSuccess?.();
    } catch {
      setErrors(["No hay conexión con el servidor. Intenta de nuevo."]);
    } finally {
      setLoading(false);
    }
  };

  const isEpisodic = form.type === "serie" || form.type === "novela";

  return (
    <div className="create-title-wrapper">
      <form className="create-title-form" onSubmit={handleSubmit}>
        {!isEditing && <h3>Agregar contenido multimedia</h3>}

        {errors.map((message) => (
          <p key={message} className="error-msg">{message}</p>
        ))}
        {successMsg && <p className="success-msg">{successMsg}</p>}

        <div className="field field--full">
          <label>Título</label>
          <input name="title" value={form.title} onChange={handleChange} required />
        </div>

        <div className="field">
          <label>Tipo</label>
          <select name="type" value={form.type} onChange={handleChange}>
            {Object.entries(TYPES).map(([value, label]) => (
              <option key={value} value={value}>{label}</option>
            ))}
          </select>
        </div>

        <div className="field">
          <label>Origen</label>
          <select name="source" value={form.source} onChange={handleChange}>
            {Object.entries(SOURCES).map(([value, label]) => (
              <option key={value} value={value}>{label}</option>
            ))}
          </select>
        </div>

        <div className="field">
          <label>Licencia</label>
          <select name="license" value={form.license} onChange={handleChange}>
            {Object.entries(LICENSES).map(([value, label]) => (
              <option key={value} value={value}>{label}</option>
            ))}
          </select>
        </div>

        <div className="field">
          <label>Clasificación</label>
          <select name="maturity" value={form.maturity} onChange={handleChange}>
            {MATURITY_OPTIONS.map((option) => (
              <option key={option} value={option}>{option}</option>
            ))}
          </select>
        </div>

        <div className="field">
          <label>Año (opcional)</label>
          <input name="year" type="number" min="1880" max="2099" value={form.year} onChange={handleChange} />
        </div>

        {!isEpisodic && (
          <div className="field">
            <label>Duración en minutos (opcional)</label>
            <input name="duration" type="number" min="1" max="1000" value={form.duration} onChange={handleChange} />
          </div>
        )}

        <fieldset className="field field--full genre-picker">
          <legend>Géneros</legend>
          <div className="genre-picker__options">
            {GENRES.map((genre) => (
              <label key={genre} className={`chip ${form.genres.includes(genre) ? "chip--on" : ""}`}>
                <input
                  type="checkbox"
                  checked={form.genres.includes(genre)}
                  onChange={() => toggleGenre(genre)}
                />
                {genre}
              </label>
            ))}
          </div>
        </fieldset>

        <div className="field field--full">
          <label>Sinopsis (opcional)</label>
          <textarea name="synopsis" rows="3" value={form.synopsis} onChange={handleChange} />
        </div>

        <div className="field field--full">
          <label>Etiquetas (opcional)</label>
          <input name="tags" placeholder="Ej.: Bela Lugosi, años 30" value={form.tags} onChange={handleChange} />
          <small>Separadas por comas. También se usan en la búsqueda.</small>
        </div>

        <div className="field field--full">
          <label>URL del póster (opcional)</label>
          <input name="poster_url" type="url" value={form.poster_url} onChange={handleChange} />
        </div>

        {!isEpisodic && (
          <>
            <div className="field field--full">
              <label>URL del video MP4 (opcional)</label>
              <input name="video_url" type="url" value={form.video_url} onChange={handleChange} />
            </div>
            <div className="field field--full">
              <label>URL de YouTube embed (opcional)</label>
              <input
                name="embed_url"
                type="url"
                placeholder="https://www.youtube-nocookie.com/embed/…"
                value={form.embed_url}
                onChange={handleChange}
              />
              <small>Se usa solo si no hay video MP4.</small>
            </div>
          </>
        )}

        {isEpisodic && (
          <p className="field--full form-note">
            Los capítulos de series y novelas vienen de los importadores
            ({initialData?.episodes?.length ?? 0} cargados) y se conservan al guardar.
          </p>
        )}

        <label className="field field--full publish-check">
          <input type="checkbox" name="published" checked={form.published} onChange={handleChange} />
          Publicado (visible en el catálogo)
        </label>

        <button type="submit" disabled={loading}>
          {loading ? "Guardando…" : isEditing ? "Guardar cambios" : "Crear contenido"}
        </button>
      </form>
    </div>
  );
}
