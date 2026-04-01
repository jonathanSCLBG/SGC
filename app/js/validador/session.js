window.REV = window.REV || {};

REV.cargarUsuarioSesion = async function cargarUsuarioSesion() {
  const resp = await fetch("../php/session_user.php", { cache: "no-store" });
  const data = await resp.json();

  if (!resp.ok || !data.ok) {
    window.location.href = "../login.html";
    return;
  }

  if (String(data.tipo_usuario || "").toLowerCase() !== "validador") {
    window.location.href = "../validador.php";
    return;
  }

  document.getElementById("lblUsuario").textContent = `${data.user_nombre} ▼`;
};
