import { createRoot } from 'react-dom/client';
import Browse from './Browse.jsx';

const container = document.getElementById('browse');

if (container) {
    const user = JSON.parse(container.dataset.user);
    // Títulos de la base de datos (los manda DashboardController en data-titles).
    const titles = JSON.parse(container.dataset.titles || '[]');
    createRoot(container).render(<Browse user={user} csrfToken={container.dataset.csrf} titles={titles} />);
}
