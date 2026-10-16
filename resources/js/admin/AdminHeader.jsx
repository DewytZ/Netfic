import { csrfToken } from "./csrf.js";

/**
 * Barra superior del panel, con el mismo estilo que la del catálogo:
 * logo, volver al catálogo, quién está conectado y cerrar sesión.
 */
export function AdminHeader({ user }) {
  const initial = (user.name ?? "?").trim().charAt(0).toUpperCase();

  return (
    <header className="admin-topbar">
      <a href="/dashboard" className="admin-topbar__logo">
        <span className="admin-topbar__dot" aria-hidden="true" />
        Netfic <span className="admin-topbar__badge">Admin</span>
      </a>

      <div className="admin-topbar__actions">
        <a href="/dashboard" className="admin-topbar__link">
          Ver catálogo
        </a>
        <div className="admin-topbar__profile" title={user.email}>
          <span className="admin-topbar__avatar" aria-hidden="true">{initial}</span>
          <span className="admin-topbar__user">{user.name}</span>
        </div>
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
