// resources/js/admin/ContentsTable.jsx
import { useState, useEffect, useCallback } from "react";
import "./Admin.css";
import ContentForm, { TYPES, SOURCES, readErrors } from "./ContentForm";
import { csrfToken } from "./csrf.js";

const PER_PAGE = 10;

/**
 * Contenido multimedia de MongoDB (lo que ve el catálogo). A diferencia de la tabla de títulos,
 * aquí la búsqueda, los filtros y la paginación los hace el servidor (/admin-api/contents).
 */
export function ContentsTable() {
  const [page, setPage] = useState({ data: [], current_page: 1, last_page: 1, total: 0 });
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState(null);

  const [filters, setFilters] = useState({ q: "", type: "", published: "" });
  const [currentPage, setCurrentPage] = useState(1);

  const [showForm, setShowForm] = useState(false);
  const [editingContent, setEditingContent] = useState(null);

  const fetchContents = useCallback(() => {
    setLoading(true);
    const params = new URLSearchParams({ page: currentPage, per_page: PER_PAGE });
    Object.entries(filters).forEach(([key, value]) => value && params.set(key, value));

    fetch(`/admin-api/contents?${params}`, { headers: { Accept: "application/json" } })
      .then(async (r) => {
        if (!r.ok) throw new Error((await readErrors(r))[0]);
        return r.json();
      })
      .then((data) => {
        setPage(data);
        setLoadError(null);
      })
      .catch((e) => setLoadError(e.message || "No se pudo cargar el contenido."))
      .finally(() => setLoading(false));
  }, [filters, currentPage]);

  // Espera a que el usuario deje de escribir antes de buscar.
  useEffect(() => {
    const timer = setTimeout(fetchContents, 250);
    return () => clearTimeout(timer);
  }, [fetchContents]);

  // Si borras el último elemento de la última página, regresamos a la anterior.
  useEffect(() => {
    if (currentPage > page.last_page) setCurrentPage(page.last_page || 1);
  }, [currentPage, page.last_page]);

  const handleFilterChange = (e) => {
    const { name, value } = e.target;
    setFilters((prev) => ({ ...prev, [name]: value }));
    setCurrentPage(1);
  };

  const send = async (url, method, body) => {
    const res = await fetch(url, {
      method,
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": csrfToken(),
      },
      body: body ? JSON.stringify(body) : undefined,
    });
    if (!res.ok) throw new Error((await readErrors(res)).join("\n"));
  };

  const handleTogglePublished = async (item) => {
    try {
      await send(`/admin-api/contents/${item.id}`, "PATCH", { published: !item.published });
      fetchContents();
    } catch (err) {
      alert(err.message);
    }
  };

  const handleDelete = async (item) => {
    if (!window.confirm(`¿Eliminar "${item.title}"? Esta acción no se puede deshacer.`)) return;
    try {
      await send(`/admin-api/contents/${item.id}`, "DELETE");
      fetchContents();
    } catch (err) {
      alert(err.message);
    }
  };

  return (
    <>
      <div className="add-resource-toggle">
        <button type="button" className="btn-toggle" onClick={() => setShowForm((prev) => !prev)}>
          {showForm ? "✖ Cancelar" : "➕ Agregar contenido"}
        </button>
      </div>

      <div className={`form-panel ${showForm ? "expanded" : "collapsed"}`}>
        {showForm && <ContentForm onSuccess={fetchContents} />}
      </div>

      <div className="admin-container">
        <div className="admin-header">
          <h2>Contenido multimedia</h2>
          {loading && <p className="loading-msg">Cargando contenido…</p>}
          {loadError && <p className="error-msg">{loadError}</p>}
          <div className="search-box">
            <input
              type="search"
              name="q"
              aria-label="Buscar por título o etiqueta"
              placeholder="Buscar por título o etiqueta..."
              value={filters.q}
              onChange={handleFilterChange}
            />
            <select name="type" aria-label="Filtrar por tipo" value={filters.type} onChange={handleFilterChange}>
              <option value="">Todos los tipos</option>
              {Object.entries(TYPES).map(([value, label]) => (
                <option key={value} value={value}>{label}</option>
              ))}
            </select>
            <select name="published" aria-label="Filtrar por estado" value={filters.published} onChange={handleFilterChange}>
              <option value="">Todos los estados</option>
              <option value="1">Publicados</option>
              <option value="0">Sin publicar</option>
            </select>
          </div>
        </div>

        <table className="admin-table">
          <thead>
            <tr>
              <th>Título</th>
              <th>Tipo</th>
              <th>Origen</th>
              <th>Géneros</th>
              <th>Año</th>
              <th>Estado</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            {page.data.length > 0 ? (
              page.data.map((item) => (
                <tr key={item.id}>
                  <td className="title-cell" data-label="Título">
                    {item.title}
                    {item.episodes?.length > 0 && (
                      <small className="cell-note">{item.episodes.length} capítulos</small>
                    )}
                  </td>
                  <td data-label="Tipo">
                    <span className={`badge ${item.type === "pelicula" ? "badge-pelicula" : "badge-serie"}`}>
                      {TYPES[item.type] ?? item.type}
                    </span>
                  </td>
                  <td data-label="Origen">{SOURCES[item.source] ?? item.source}</td>
                  <td data-label="Géneros">{(item.genres ?? []).join(", ") || "—"}</td>
                  <td data-label="Año">{item.year ?? "—"}</td>
                  <td data-label="Estado">
                    <button
                      type="button"
                      className={`status-toggle ${item.published ? "status-toggle--on" : ""}`}
                      title={item.published ? "Clic para ocultar del catálogo" : "Clic para publicar"}
                      onClick={() => handleTogglePublished(item)}
                    >
                      {item.published ? "Publicado" : "Oculto"}
                    </button>
                    {item.maturity === "18+" && <small className="cell-note">18+: nunca visible</small>}
                  </td>
                  <td className="action-cell">
                    <button className="btn-edit" onClick={() => setEditingContent(item)}>
                      Editar
                    </button>
                    <button className="btn-delete" onClick={() => handleDelete(item)}>
                      Eliminar
                    </button>
                  </td>
                </tr>
              ))
            ) : (
              <tr>
                <td colSpan="7" className="no-results">
                  {loading ? "Cargando…" : "No hay contenido que coincida con los filtros."}
                </td>
              </tr>
            )}
          </tbody>
        </table>

        <div className="admin-pagination">
          <span className="pagination-info">
            Página <strong>{page.current_page}</strong> de <strong>{page.last_page}</strong> ({page.total} elementos)
          </span>
          <div className="pagination-buttons">
            <button className="btn-page" disabled={currentPage <= 1} onClick={() => setCurrentPage(currentPage - 1)}>
              &laquo; Anterior
            </button>
            <button
              className="btn-page"
              disabled={currentPage >= page.last_page}
              onClick={() => setCurrentPage(currentPage + 1)}
            >
              Siguiente &raquo;
            </button>
          </div>
        </div>
      </div>

      {editingContent && (
        <div className="modal-overlay">
          <div className="modal-content" role="dialog" aria-modal="true" aria-labelledby="edit-content-heading">
            <h3 id="edit-content-heading">Editar contenido</h3>
            <ContentForm
              key={editingContent.id}
              initialData={editingContent}
              onSuccess={() => {
                setEditingContent(null);
                fetchContents();
              }}
            />
            <button className="btn-cancel" onClick={() => setEditingContent(null)}>
              Cancelar
            </button>
          </div>
        </div>
      )}
    </>
  );
}
