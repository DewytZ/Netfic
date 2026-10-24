import { useEffect, useRef } from 'react';

/**
 * Capa a pantalla completa (ficha o reproductor): bloquea el scroll, cierra con Escape
 * y devuelve el foco al elemento anterior al cerrarse. Devuelve una ref para el botón de cerrar.
 */
export function useOverlay(onClose) {
    const closeRef = useRef(null);

    useEffect(() => {
        const previous = document.activeElement;
        const onKey = (event) => event.key === 'Escape' && !document.fullscreenElement && onClose();
        document.body.classList.add('nf-lock');
        document.addEventListener('keydown', onKey);
        closeRef.current?.focus();

        return () => {
            document.body.classList.remove('nf-lock');
            document.removeEventListener('keydown', onKey);
            previous?.focus?.();
        };
    }, [onClose]);

    return closeRef;
}
