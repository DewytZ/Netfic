// resources/js/admin/TitlesTable.jsx
import { useState, useEffect } from "react";
import "./Admin.css";
import CreateTitleForm from "./CreateTitleForm";

export function TitlesTable() {
  /* -------------------- ESTADOS -------------------- */
  const [titles, setTitles] = useState([]);
  const [loading, setLoading] = useState(true);

  const [searchTerm, setSearchTerm] = useState("");
  const [currentPage, setCurrentPage] = useState(1);
  const itemsPerPage = 5;

  // Nuevo estado: controla si el formulario está visible
  const [showForm, setShowForm] = useState(false);

  /* -------------------- FETCH DE TÍTULOS -------------------- */
  const fetchTitles = () => {
    setLoading(true);
    fetch("/api/titles", { method: "GET" })
      .then((r) => r.json())
      .then((data) => {
        setTitles(data);
        setLoading(false);
      })
      .catch((e) => {
        console.error("Error al consultar Supabase:", e);
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

  /* -------------------- HANDLERS -------------------- */
  const handleSearchChange = (e) => {
    setSearchTerm(e.target.value);
    setCurrentPage(1);
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
          <div className="search-box">
            <input
              type="text"
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
            </tr>
          </thead>
          <tbody>
            {currentTitles.length > 0 ? (
              currentTitles.map((item) => (
                <tr key={item.id}>
                  <td>{item.id}</td>
                  <td className="title-cell">{item.title}</td>
                  <td>
                    <span
                      className={`badge ${
                        item.type === "Serie" ? "badge-serie" : "badge-pelicula"
                      }`}
                    >
                      {item.type}
                    </span>
                  </td>
                  <td>{item.genre}</td>
                  <td>{item.year}</td>
                  <td>{item.duration}</td>
                </tr>
              ))
            ) : (
              <tr>
                <td colSpan="6" className="no-results">
                  No se encontraron títulos que coincidan con "{searchTerm}".
                </td>
              </tr>
            )}
          </tbody>
        </table>

        {/* Paginación */}
        <div className="admin-pagination">
          <span className="pagination-info">
            Página <strong>{currentPage}</strong> de <strong>{totalPages}</strong>
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
    </>
  );
}