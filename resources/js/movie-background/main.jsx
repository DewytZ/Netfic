import { createRoot } from 'react-dom/client';
import MovieBackground from './MovieBackground.jsx';

const container = document.getElementById('movie-background');

if (container) {
    createRoot(container).render(<MovieBackground />);
}
