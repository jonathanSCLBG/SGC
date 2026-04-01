window.PRE = window.PRE || {};

/* ============================================================
   DOM Ready (archivo principal de arranque)
   ============================================================ */
document.addEventListener("DOMContentLoaded", async () => {

  // Tooltips
  document
    .querySelectorAll('[data-bs-toggle="tooltip"]')
    .forEach(el => new bootstrap.Tooltip(el));

  // 🔥 Cargar usuario (esto quita el "Cargando...")
  if (typeof PRE.cargarUsuarioSesion === "function") {
    await PRE.cargarUsuarioSesion();
  } else {
    console.warn("PRE.cargarUsuarioSesion no existe.");
  }

  // Cargar primera sección
  if (typeof PRE.cargarCuestionario === "function") {
    PRE.cargarCuestionario(1);
  } else {
    console.warn("PRE.cargarCuestionario no existe.");
  }
});
