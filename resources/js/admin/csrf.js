// El token CSRF está en la etiqueta <meta name="csrf-token"> de admin/index.blade.php.
// Laravel lo exige en cada petición que cambie datos (POST, PUT, DELETE).
export const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? "";
