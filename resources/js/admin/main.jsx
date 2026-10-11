import { createRoot } from "react-dom/client";
import { TitlesTable } from "./TitlesTable.jsx";

// Busca el elemento en el HTML donde se va a pintar nuestra tabla
const container = document.getElementById('admin-root');

if (container) {
    const root = createRoot(container);
    root.render(<TitlesTable/>);
}

{/* ¿Que hace esto?  busca una etiqueta "admin-root" en la pagina. en cuanto la encuentra inscrusta ahí
    adentro nuestro componente TitlesTable*/}