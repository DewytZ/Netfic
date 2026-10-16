import { csrfToken } from "./csrf.js";

/**
 * Barra superior del panel: quién está conectado, volver al catálogo y cerrar sesión.
 */
export function AdminHeader({ user }) {
  return (
    <header className="admin-topbar">
      <a href="/dashboard" className="admin-topbar__logo">
        <span className="admin-topbar__dot" aria-hidden="true" />
        Netfic <span className="admin-topbar__badge">Admin</span>
      </a>

      <div className="admin-topbar__actions">
        <span className="admin-topbar__user" title={user.email}>
          {user.name}
        </span>
        <a href="/dashboard" className="admin-topbar__link">
          Ver catálogo
        </a>
        <form method="POST" action="/logout">
          <input type="hidden" name="_token" value={csrfToken()} />
          <button type="submit" className="admin-topbar__logout">
            Cerrar sesión
          </button>
        </form>
      </div>
    </header>
  );
}
