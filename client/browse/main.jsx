import { createRoot } from 'react-dom/client';
import Browse from './Browse.jsx';

const container = document.getElementById('browse');

if (container) {
    const user = JSON.parse(container.dataset.user);
    createRoot(container).render(<Browse user={user} csrfToken={container.dataset.csrf} />);
}
