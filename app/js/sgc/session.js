window.PRE = window.PRE || {};

PRE.cargarUsuarioSesion = async function cargarUsuarioSesion() {
  try {
    const resp = await fetch("../php/session_user.php", { cache: "no-store" });
    const data = await resp.json();

    if (!resp.ok || !data.ok) {
      window.location.href = "../login.html";
      return;
    }

    const lbl = document.getElementById("lblUsuario");
    if (!lbl) return;

    // Tu sgc.html (preparador) trae badge como hijo => mantenemos estructura si existe
    if (lbl.childNodes && lbl.childNodes.length > 0) {
      // Primer nodo de texto
      lbl.childNodes[0].textContent = `${data.user_nombre} ▼ `;
    } else {
      lbl.textContent = `${data.user_nombre} ▼`;
    }

  } catch (e) {
    console.error("Error cargando sesión:", e);
    const lbl = document.getElementById("lblUsuario");
    if (lbl) lbl.textContent = "Usuario ▼";
  }
};
