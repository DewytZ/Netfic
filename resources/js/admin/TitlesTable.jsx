import { useState } from "react";
import { CATALOG } from "../browse/catalog.js";
import "./Admin.css";

export function TitlesTable(){
    //1.- Estado para lo que el usuario escribe en el buscador (empieza vacio " ")
    const [searchTerm, setSearchTerm] = useState('');

    //2.- Estado para saber en que pagina estamos (empieza en la pagina 1)
    const [currentPage, setCurrentPage] = useState(1);

    //3.- cuantas peliculas mostramos por pagina
    const itemsPerPage =5;

    //4.- Filtrar el catalogo segun lo que el usuario eescribió
    const filteredTitles = CATALOG.filter((item) =>{
        const busqueda = searchTerm.toLowerCase();
        const coincideTitulo = item.title.toLowerCase().includes(busqueda);
        const coincideGenero = item.genre.toLowerCase().includes(busqueda);

        return coincideGenero || coincideTitulo
    });

    //5.- calcular el total de paginas necesarias
    const totalPages = Math.ceil(filteredTitles.length / itemsPerPage) || 1;

    //6.- recortar solo las peliculas de la pagina actual (recordar desde que pelicula se mostrará en la pagina)
    const startIndex = (currentPage - 1) * itemsPerPage;
    const currentTitles = filteredTitles.slice(startIndex, startIndex + itemsPerPage);

    const handleSearchChange = (e) => {
        setSearchTerm(e.target.value);
        setCurrentPage(1);
    };

    return (
        <div className="admin-container">
            {/* Encabezado y Buscador */}
            <div className="admin-header">
                <h2>Administración de Títulos</h2>
                <div className="search-box">
                    <input
                    type="text"
                    placeholder="Buscar por título o género..."
                    value={searchTerm}
                    onChange={handleSearchChange}
                    />
                    {searchTerm && (
                        <button onClick={() => { setSearchTerm(''); setCurrentPage(1);}}>
                            Limpiar
                        </button>
                    )}
                </div>
            </div>

            {/* TABLA DE RESULTADOS */}
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
                                    <span className={`badge ${item.type === 'Serie' ? 'badge-serie' : 'badge-pelicula'}`}>
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
            <div className="admin-pagination">
                <span className="pagination-info">
                    Página <strong>{currentPage}</strong> de <strong>{totalPages}</strong>
                    ({filteredTitles.length} títulos)
                </span>

                <div className="pagination-buttons">
                    {/*Botón anterior */}
                    <button
                        className="btn-page"
                        disabled={currentPage === 1}
                        onClick={() => setCurrentPage(currentPage - 1)}
                    >
                        &laquo; Anterior
                    </button>
                    {/* Botones numéricos (1, 2, 3...) */}
                    {Array.from({length: totalPages}, (_, index) => {
                        const pageNum = index + 1;
                        return (
                            <button
                            key={pageNum}
                            className={`btn-page ${currentPage === pageNum ? 'btn-page-active' : ''}`}
                            onClick={() => setCurrentPage(pageNum)}
                            >
                                {pageNum}
                            </button>
                        );
                    })}

                    {/*Botón siguiente */}
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
    );


}