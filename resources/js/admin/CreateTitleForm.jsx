// resources/js/admin/CreateTitleForm.jsx
import { useState } from "react";
import { csrfToken } from "./csrf.js";

// Mismos géneros y clasificaciones que tiene la base de datos (database/seeders/TitleSeeder.php).
const GENRES = [
  "Acción",
  "Aventura",
  "Ciencia ficción",
  "Crimen",
  "Documental",
  "Drama",
  "Fantasía",
  "Misterio",
  "Romance",
  "Terror",
  "Thriller",
];
const MATURITY_OPTIONS = ["TP", "7+", "13+", "16+", "18+"];

const EMPTY_FORM = {
  title: "",
  type: "Película",
  genre: "",
  year: "",
  hours: "",      // solo para películas
  minutes: "",    // solo para películas
  seasons: "",    // solo para series
  description: "",
  maturity: "13+",
  colors: "",     // texto separado por comas; se convierte en arreglo al enviar
};

/* ---------- Conversión entre el formulario y la API ---------- */

// "2 h 08 min" -> { hours: "2", minutes: "8" }
function parseDuration(text) {
  const source = text ?? "";
  return {
    hours: source.match(/(\d+)\s*h/)?.[1] ?? "",
    minutes: source.match(/(\d+)\s*min/)?.[1] ?? "",
  };
}

// { hours: "2", minutes: "8" } -> "2 h 08 min", igual que en el seed
function formatDuration(hours, minutes) {
  return `${Number(hours)} h ${String(Number(minutes)).padStart(2, "0")} min`;
}

// Registro de la API -> valores del formulario (para editar)
function toFormState(title) {
  return {
    title: title.title ?? "",
    type: title.type ?? "Película",
    genre: title.genre ?? "",
    year: String(title.year ?? ""),
    ...parseDuration(title.duration),
    seasons: title.seasons ? String(title.seasons) : "",
    description: title.description ?? "",
    maturity: title.maturity ?? "13+",
    colors: Array.isArray(title.colors) ? title.colors.join(", ") : "",
  };
}

// Valores del formulario -> cuerpo que espera la API
function buildPayload(form) {
  const isMovie = form.type === "Película";
  const colors = form.colors
    .split(",")
    .map((color) => color.trim())
    .filter(Boolean);

  return {
    title: form.title.trim(),
    type: form.type,
    genre: form.genre,
    year: Number(form.year),
    description: form.description.trim() || null,
    maturity: form.maturity,
    colors: colors.length > 0 ? colors : null,
    // Cada tipo usa solo su campo; el otro se envía vacío.
    duration: isMovie ? formatDuration(form.hours, form.minutes) : null,
    seasons: isMovie ? null : Number(form.seasons),
  };
}

// Laravel responde { message, errors: { campo: [mensajes] } } cuando falla la validación.
async function readErrors(response) {
  if (response.status === 401) return ["Tu sesión expiró. Vuelve a iniciar sesión."];
  if (response.status === 419) return ["La página caducó. Recárgala e intenta de nuevo."];

  const data = await response.json().catch(() => ({}));
  if (data.errors) return Object.values(data.errors).flat();

  return [data.message || `Error ${response.status}`];
}

/**
 * Formulario para crear un título o, si recibe `initialData`, para editarlo.
 * @param {{initialData?: object, onSuccess: () => void}} props
 */
export default function CreateTitleForm({ initialData, onSuccess }) {
  const isEditing = Boolean(initialData);

  // ---------- Estado ----------
  const [form, setForm] = useState(() => (isEditing ? toFormState(initialData) : EMPTY_FORM));
  const [loading, setLoading] = useState(false);
  const [errors, setErrors] = useState([]);
  const [successMsg, setSuccessMsg] = useState(null);

  // ---------- Manejo de cambios ----------
  const handleChange = (e) => {
    const { name, value } = e.target;
    setForm((prev) => ({ ...prev, [name]: value }));
  };

  // ---------- Envío: POST para crear, PUT para editar ----------
  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setErrors([]);
    setSuccessMsg(null);

    const url = isEditing ? `/api/titles/${initialData.id}` : "/api/titles";

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
        onSuccess?.(); // el modal se cierra y la tabla se actualiza
        return;
      }

      setSuccessMsg("✅ Título creado correctamente");
      setForm(EMPTY_FORM);
      onSuccess?.();
    } catch {
      setErrors(["No hay conexión con el servidor. Intenta de nuevo."]);
    } finally {
      setLoading(false);
    }
  };

  // ---------- Render ----------
  return (
    <div className="create-title-wrapper">
      <form className="create-title-form" onSubmit={handleSubmit}>
        {!isEditing && <h3>Crear nuevo título</h3>}

        {errors.map((message) => (
          <p key={message} className="error-msg">{message}</p>
        ))}
        {successMsg && <p className="success-msg">{successMsg}</p>}

        {/* ----- Campos ----- */}
        <div className="field">
          <label>Título</label>
          <input name="title" value={form.title} onChange={handleChange} required />
        </div>

        <div className="field">
          <label>Tipo</label>
          <select name="type" value={form.type} onChange={handleChange}>
            <option value="Película">Película</option>
            <option value="Serie">Serie</option>
          </select>
        </div>

        <div className="field">
          <label>Género</label>
          <select name="genre" value={form.genre} onChange={handleChange} required>
            <option value="">Selecciona un género</option>
            {GENRES.map((genre) => (
              <option key={genre} value={genre}>{genre}</option>
            ))}
          </select>
        </div>

        <div className="field">
          <label>Año</label>
          <input
            name="year"
            type="number"
            min="1900"
            max="2099"
            value={form.year}
            onChange={handleChange}
            required
          />
        </div>

        {/* ----- Campos condicionales ----- */}
        {form.type === "Película" && (
          <>
            <div className="field">
              <label>Horas</label>
              <input
                name="hours"
                type="number"
                min="0"
                value={form.hours}
                onChange={handleChange}
                required
              />
            </div>
            <div className="field">
              <label>Minutos</label>
              <input
                name="minutes"
                type="number"
                min="0"
                max="59"
                value={form.minutes}
                onChange={handleChange}
                required
              />
            </div>
          </>
        )}

        {form.type === "Serie" && (
          <div className="field">
            <label>Número de temporadas</label>
            <input
              name="seasons"
              type="number"
              min="1"
              value={form.seasons}
              onChange={handleChange}
              required
            />
          </div>
        )}

        <div className="field">
          <label>Descripción (opcional)</label>
          <textarea
            name="description"
            rows="3"
            value={form.description}
            onChange={handleChange}
          />
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
          <label>Colores (opcional)</label>
          <input
            name="colors"
            placeholder="Ej.: #1e3a8a, #7c3aed"
            value={form.colors}
            onChange={handleChange}
          />
          <small>Escribe los colores separados por comas, o déjalo vacío.</small>
        </div>

        {/* ----- Botón ----- */}
        <button type="submit" disabled={loading}>
          {loading ? "Guardando…" : isEditing ? "Guardar cambios" : "Crear título"}
        </button>
      </form>
    </div>
  );
}
