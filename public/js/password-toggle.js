// Agrega un botón "ver contraseña" a cada campo de contraseña dentro de un .input-group.
const EYE = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>';
const EYE_OFF = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.7 5.1A10.4 10.4 0 0 1 12 5c6.5 0 10 7 10 7a17.6 17.6 0 0 1-2.2 3.2M6.6 6.6C3.7 8.5 2 12 2 12s3.5 7 10 7a9.7 9.7 0 0 0 5.4-1.6"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/><path d="M2 2l20 20"/></svg>';

document.querySelectorAll('.input-group input[type="password"]').forEach((input) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'password-toggle';

    const render = () => {
        const visible = input.type === 'text';
        button.innerHTML = visible ? EYE_OFF : EYE;
        button.setAttribute('aria-label', visible ? 'Ocultar contraseña' : 'Mostrar contraseña');
        button.setAttribute('aria-pressed', String(visible));
    };

    button.addEventListener('click', () => {
        input.type = input.type === 'password' ? 'text' : 'password';
        render();
        input.focus();
    });

    // Al enviar el formulario se vuelve a ocultar, para que el navegador no la guarde como texto.
    input.form?.addEventListener('submit', () => {
        input.type = 'password';
    });

    render();
    input.parentElement.classList.add('has-toggle');
    input.parentElement.appendChild(button);
});
