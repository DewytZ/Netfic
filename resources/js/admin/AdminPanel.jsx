// resources/js/admin/AdminPanel.jsx
import { useState } from "react";
import { ContentsTable } from "./ContentsTable.jsx";
import { TitlesTable } from "./TitlesTable.jsx";

const TABS = {
  contents: "Contenido multimedia",
  titles: "Títulos (base SQL)",
};

/**
 * Pestañas del panel. "Contenido multimedia" (MongoDB) va primero porque es lo que muestra el catálogo;
 * "Títulos" administra la tabla titles de la base SQL, que el catálogo también incluye.
 */
export function AdminPanel() {
  const [tab, setTab] = useState("contents");

  return (
    <>
      <nav className="admin-tabs" role="tablist">
        {Object.entries(TABS).map(([key, label]) => (
          <button
            key={key}
            type="button"
            role="tab"
            aria-selected={tab === key}
            className={`admin-tabs__tab ${tab === key ? "admin-tabs__tab--active" : ""}`}
            onClick={() => setTab(key)}
          >
            {label}
          </button>
        ))}
      </nav>

      {tab === "contents" ? <ContentsTable /> : <TitlesTable />}
    </>
  );
}
