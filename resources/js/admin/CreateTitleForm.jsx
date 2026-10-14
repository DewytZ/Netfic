// resources/js/admin/CreateTitleForm.jsx
import { useState } from "react";

/**
 * Formulario para crear un nuevo título.
 * @param {{onSuccess: () => void}} props
 */
export default function CreateTitleForm({ onSuccess }) {
  // ---------- Estado de los campos ----------
  const [form, setForm] = useState({
    title: "",
    type: "Película",
    genre: "",
    year: "",
    /*** NUEVOS CAMPOS ***/
    hours: "",      // solo para películas
    minutes: "",    // solo para películas
    seasons: "",    // solo para series
    /*** FIN NUEVOS CAMPOS ***/
    description: "",
    maturity: "13+",
    colors: "",
  });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [successMsg, setSuccessMsg] = useState(null);

  // ---------- Manejo de cambios ----------
  const handleChange = (e) => {
    const { name, value } = e.target;
    setForm((prev) => ({ ...prev, [name]: value }));
  };

  // ---------- Envío del formulario ----------
  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError(null);
    setSuccessMsg(null);

    // Armar payload según el tipo seleccionado
    const basePayload = {
      ...form,
      colors: form.colors.trim() === "" ? null : form.colors,
    };
    // Si es película, construimos `duration` a partir de horas/minutos
    if (form.type === "Película") {
      const h = form.hours ? `${form.hours}h` : "";
      const m = form.minutes ? ` ${form.minutes}m` : "";
      basePayload.duration = (h + m).trim();
      delete basePayload.seasons;               // no se envía
    } else {
      // Serie
      basePayload.seasons = form.seasons ? parseInt(form.seasons) : null;
      delete basePayload.hours;
      delete basePayload.minutes;
    }
    // Eliminamos los auxiliares que no existen en la tabla
    delete basePayload.hours;
    delete basePayload.minutes;

    try {
      const response = await fetch("/api/titles", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(basePayload),
      });

      if (!response.ok) {
        const err = await response.json();
        throw new Error(err.message || `HTTP ${response.status}`);
      }

      setSuccessMsg("✅ Título creado correctamente");
      setForm({
        title: "",
        type: "Película",
        genre: "",
        year: "",
        hours: "",
        minutes: "",
        seasons: "",
        description: "",
        maturity: "13+",
        colors: "",
      });
      if (onSuccess) onSuccess();
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  // ---------- Render ----------
  return (
    <div className="create-title-wrapper">
      <form className="create-title-form" onSubmit={handleSubmit}>
        <h3>Crear nuevo título</h3>

        {error && <p className="error-msg">{error}</p>}
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
          <select name="genre" value={form.genre} onChange={handleChange}>
            <option value="">Selecciona un género</option>
            <option value="Acción">Acción</option>
            <option value="Terror / Suspenso">Terror / Suspenso</option>
            <option value="Comedia">Comedia</option>
            <option value="Drama">Drama</option>
            <option value="Ciencia Ficción">Ciencia Ficción</option>
            <option value="Fantasía">Fantasía</option>
            <option value="Romance">Romance</option>
            <option value="Musical">Musical</option>
          </select>
        </div>

        <div className="field">
          <label>Año</label>
          <input
            name="year"
            type="number"
            min="1900"
            max="2100"
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

        {/* El campo *duration* ya no se muestra; lo genera el código anterior. */}

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
            <option value="13+">13+</option>
            <option value="16+">16+</option>
            <option value="18+">18+</option>
          </select>
        </div>

        <div className="field">
          <label>Colores (JSON) – opcional</label>
          <input
            name="colors"
            placeholder='Ej.: ["rojo","azul"]'
            value={form.colors}
            onChange={handleChange}
          />
          <small>Escriba un array JSON o deje vacío.</small>
        </div>

        {/* ----- Botón ----- */}
        <button type="submit" disabled={loading}>
          {loading ? "Creando…" : "Crear título"}
        </button>
      </form>
    </div>
  );
}