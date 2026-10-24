// resources/js/admin/TitlesTable.jsx
import { useState, useEffect } from "react";
import "./Admin.css";
import CreateTitleForm from "./CreateTitleForm";
import { csrfToken } from "./csrf.js";

// Las películas muestran su duración; las series, su número de temporadas.
// Los títulos de series guardados antes tenían las temporadas escritas en "duration", por eso ese es el respaldo.
function formatDurationCell(item) {
  if (item.type === "Serie" && item.seasons) {
    return `${item.seasons} ${item.seasons === 1 ? "temporada" : "temporadas"}`;
  }
  return item.duration ?? "—";
}

export function TitlesTable() {
  /* -------------------- ESTADOS -------------------- */
  const [titles, setTitles] = useState([]);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState(null);

  const [searchTerm, setSearchTerm] = useState("");
  const [currentPage, setCurrentPage] = useState(1);
  const itemsPerPage = 5;

  // Nuevo estado: controla si el formulario está visible
  const [showForm, setShowForm] = useState(false);

  // guarda el titulo a editar
  const [editingTitle, setEditingTitle] = useState(null);

  // estado para mostrar o ocultar el panel de edicion
  const [showEditModal, setShowEditModal] = useState(false);

  /* -------------------- FETCH DE TÍTULOS -------------------- */
  const fetchTitles = () => {
    setLoading(true);
    fetch("/api/titles", { method: "GET", headers: { Accept: "application/json" } })
      .then((r) => {
        if (!r.ok) throw new Error("No se pudieron cargar los títulos. Intenta recargar la página.");
        return r.json();
      })
      .then((data) => {
        setTitles(data);
        setLoadError(null);
        setLoading(false);
      })
      .catch((e) => {
        console.error("Error al cargar los títulos:", e);
        setLoadError(e.message);
        setLoading(false);
      });
  };

  useEffect(() => {
    fetchTitles();
  }, []);

  /* -------------------- FILTRADO -------------------- */
  const filteredTitles = titles.filter((item) => {
    const busqueda = searchTerm.toLowerCase();
    const coincideTitulo = item.title.toLowerCase().includes(busqueda);
    const coincideGenero = item.genre.toLowerCase().includes(busqueda);
    return coincideGenero || coincideTitulo;
  });

  const totalPages = Math.ceil(filteredTitles.length / itemsPerPage) || 1;
  const startIndex = (currentPage - 1) * itemsPerPage;
  const currentTitles = filteredTitles.slice(startIndex, startIndex + itemsPerPage);

  // Si borras el último título de la última página, regresamos a la página anterior.
  useEffect(() => {
    if (currentPage > totalPages) setCurrentPage(totalPages);
  }, [currentPage, totalPages]);

  /* -------------------- HANDLERS -------------------- */
  const handleSearchChange = (e) => {
    setSearchTerm(e.target.value);
    setCurrentPage(1);
  };
//Eliminar titulo
  const handleDeleteClick = async (id) => {
    if (!window.confirm('¿Estás seguro de eliminar este título?')) return;
  try {
    
    const res = await fetch(`/admin-api/titles/${id}`, {
      method: 'DELETE',
      headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
    });
    if (!res.ok) throw new Error('No se pudo eliminar el título');
    // Si la eliminación fue exitosa, refrescamos la lista.
    fetchTitles();
  } catch (err) {
    alert(err.message);
    }
  };

  const handleEditClick = (title) => {
  // Guardamos el registro que se quiere editar y abrimos el modal.
  setEditingTitle(title);
  setShowEditModal(true);
  };

  /* -------------------- RENDER -------------------- */
  return (
    <>
      {/* ---------- BOTÓN DESPLEGABLE ---------- */}
      <div className="add-resource-toggle">
        <button
          type="button"
          className="btn-toggle"
          onClick={() => setShowForm((prev) => !prev)}
        >
          {showForm ? "✖ Cancelar" : "➕ Agregar nuevo recurso"}
        </button>
      </div>

      {/* ---------- PANEL CON EL FORMULARIO ---------- */}
      <div className={`form-panel ${showForm ? "expanded" : "collapsed"}`}>
        {showForm && <CreateTitleForm onSuccess={fetchTitles} />}
      </div>

      {/* ---------- TABLA DE RESULTADOS ---------- */}
      <div className="admin-container">
        {/* Encabezado y buscador */}
        <div className="admin-header">
          <h2>Administración de Títulos</h2>
          {loading && <p className="loading-msg">Cargando títulos…</p>}
          {loadError && <p className="error-msg">{loadError}</p>}
          <div className="search-box">
            <input
              type="search"
              aria-label="Buscar por título o género"
              placeholder="Buscar por título o género..."
              value={searchTerm}
              onChange={handleSearchChange}
            />
            {searchTerm && (
              <button
                onClick={() => {
                  setSearchTerm("");
                  setCurrentPage(1);
                }}
              >
                Limpiar
              </button>
            )}
          </div>
        </div>

        {/* Tabla */}
        <table className="admin-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Título</th>
              <th>Tipo</th>
              <th>Género</th>
              <th>Año</th>
              <th>Duración</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            {currentTitles.length > 0 ? (
              currentTitles.map((item) => (
                <tr key={item.id}>
                  <td data-label="ID">{item.id}</td>
                  <td className="title-cell" data-label="Título">{item.title}</td>
                  <td data-label="Tipo">
                    <span
                      className={`badge ${
                        item.type === "Serie" ? "badge-serie" : "badge-pelicula"
                      }`}
                    >
                      {item.type}
                    </span>
                  </td>
                  <td data-label="Género">{item.genre}</td>
                  <td data-label="Año">{item.year}</td>
                  <td data-label="Duración">{formatDurationCell(item)}</td>
                  <td className="action-cell">
                    <button className="btn-edit"
                    onClick={() => handleEditClick(item)}
                    >
                      Editar
                    </button>

                    <button className="btn-delete"
                    onClick={() => handleDeleteClick(item.id)}>
                      Eliminar
                    </button>

                  </td>
                </tr>
              ))
            ) : (
              <tr>
                <td colSpan="7" className="no-results">
                  No se encontraron títulos que coincidan con "{searchTerm}".
                </td>
              </tr>
            )}
          </tbody>
        </table>

        {/* Paginación */}
        <div className="admin-pagination">
          <span className="pagination-info">
            Página <strong>{currentPage}</strong> de <strong>{totalPages}</strong>{" "}
            ({filteredTitles.length} títulos)
          </span>

          <div className="pagination-buttons">
            {/* Anterior */}
            <button
              className="btn-page"
              disabled={currentPage === 1}
              onClick={() => setCurrentPage(currentPage - 1)}
            >
              &laquo; Anterior
            </button>

            {/* Números */}
            {Array.from({ length: totalPages }, (_, index) => {
              const pageNum = index + 1;
              return (
                <button
                  key={pageNum}
                  className={`btn-page ${
                    currentPage === pageNum ? "btn-page-active" : ""
                  }`}
                  onClick={() => setCurrentPage(pageNum)}
                >
                  {pageNum}
                </button>
              );
            })}

            {/* Siguiente */}
            <button
              className="btn-page"
              disabled={currentPage === totalPages}
              onClick={() => setCurrentPage(currentPage + 1)}
            >
              Siguiente &raquo;
            </button>
          </div>
        </div>
      </div>
      {/* ---------- MODAL DE EDICIÓN ---------- */}
{showEditModal && editingTitle && (
  <div className="modal-overlay">
    <div className="modal-content" role="dialog" aria-modal="true" aria-labelledby="edit-title-heading">
      <h3 id="edit-title-heading">Editar título</h3>

      {/* Reutilizamos el mismo formulario. Le pasamos los datos del registro
          a través de `initialData`. */}
      <CreateTitleForm
        key={editingTitle.id}
        onSuccess={() => {
          // al guardar, cerramos el modal y refrescamos la tabla
          setShowEditModal(false);
          setEditingTitle(null);
          fetchTitles();
        }}
        initialData={editingTitle}
      />

      <button
        className="btn-cancel"
        onClick={() => {
          setShowEditModal(false);
          setEditingTitle(null);
        }}
      >
        Cancelar
      </button>
    </div>
  </div>
)}
    </>
  );
}