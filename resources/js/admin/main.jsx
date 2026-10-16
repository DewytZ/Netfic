import { createRoot } from "react-dom/client";
import { AdminHeader } from "./AdminHeader.jsx";
import { TitlesTable } from "./TitlesTable.jsx";

// Busca el elemento en el HTML donde se va a pintar el panel.
const container = document.getElementById('admin-root');

if (container) {
    // Datos del administrador conectado (los manda admin/index.blade.php en data-user).
    const user = JSON.parse(container.dataset.user || '{}');
    const root = createRoot(container);
    root.render(
        <>
            <AdminHeader user={user} />
            <TitlesTable />
        </>
    );
}
