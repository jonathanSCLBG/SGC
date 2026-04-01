window.PRE = window.PRE || {};

/* ============================================================
   Pendientes por sección (badges en tabs)
   ============================================================ */
PRE.marcarPendientesSecciones = async function marcarPendientesSecciones() {
  const { cid } = PRE.getParams();
  if (!cid) return;

  const url = new URL("../php/pendientes_notas.php", window.location.href);
  url.searchParams.set("cid", cid);

  const resp = await fetch(url.toString(), { cache: "no-store" });
  const data = await resp.json();
  if (!resp.ok || !data.ok) return;

  const mapa = data.pendientes_por_seccion || {};
  document.querySelectorAll(".nav-link .badge").forEach((b) => b.remove());

  for (let i = 1; i <= 8; i++) {
    const key = `pagina_${i}`;
    const pendientes = Number(mapa[key] || 0);
    if (pendientes > 0) {
      const tab = document.querySelectorAll(".nav-link")[i - 1];
      if (!tab) continue;

      const badge = document.createElement("span");
      badge.className = "badge bg-danger ms-2";
      badge.textContent = pendientes;
      tab.appendChild(badge);
    }
  }
};

/* ============================================================
   Pendientes por pregunta (badge en botón 📝)
   ❌ SIN botón extra en la tarjeta
   ============================================================ */
PRE.marcarPendientesPreguntas = async function marcarPendientesPreguntas() {
  const { cid } = PRE.getParams();
  if (!cid) return;

  const seccion = `pagina_${PRE.state.seccionActual}`;

  const url = new URL("../php/pendientes_notas.php", window.location.href);
  url.searchParams.set("cid", cid);
  url.searchParams.set("seccion", seccion);

  const resp = await fetch(url.toString(), { cache: "no-store" });
  const data = await resp.json();
  if (!resp.ok || !data.ok) return;

  const mapa = data.pendientes_por_pregunta || {};

  document.querySelectorAll(".pregunta[data-pregunta]").forEach((b) => {
    const pNum = b.getAttribute("data-pregunta");
    const btnNota = b.querySelector(".btn-nota");
    if (!btnNota) return;

    const pendientes = Number(mapa[pNum] || 0);

    // Reset
    btnNota.classList.remove("btn-outline-danger");
    btnNota.classList.add("btn-outline-blue");
    btnNota.removeAttribute("data-badge");

    if (pendientes > 0) {
      btnNota.classList.remove("btn-outline-blue");
      btnNota.classList.add("btn-outline-danger");
      btnNota.setAttribute("data-badge", String(pendientes));
    }
  });
};

/* ============================================================
   Total pendientes (badge sobre usuario)
   ============================================================ */
PRE.actualizarTotalPendientes = async function actualizarTotalPendientes() {
  const { cid } = PRE.getParams();
  if (!cid) return;

  const url = new URL("../php/pendientes_notas.php", window.location.href);
  url.searchParams.set("cid", cid);

  const resp = await fetch(url.toString(), { cache: "no-store" });
  const data = await resp.json();

  const badge = document.getElementById("badgeTotalPendientes");
  if (!badge) return;

  if (!resp.ok || !data.ok) {
    badge.style.display = "none";
    return;
  }

  const total = Number(data.total_pendientes || 0);
  if (total > 0) {
    badge.textContent = total;
    badge.style.display = "inline-block";
  } else {
    badge.style.display = "none";
  }
};

/* ============================================================
   Modal pendientes + “Ir”
   ============================================================ */
PRE.abrirPendientes = async function abrirPendientes() {
  const { cid } = PRE.getParams();
  if (!cid) return;

  if (!PRE.state.modalPendientes) {
    PRE.state.modalPendientes = new bootstrap.Modal(document.getElementById("modalPendientes"));
  }

  document.getElementById("listaPendientes").innerHTML = "<div class='text-muted'>Cargando...</div>";
  PRE.state.modalPendientes.show();

  await PRE.cargarListaPendientes(cid);
};

window.abrirPendientes = PRE.abrirPendientes;

PRE.cargarListaPendientes = async function cargarListaPendientes(cid) {
  const url = new URL("../php/listar_pendientes.php", window.location.href);
  url.searchParams.set("cid", cid);

  const resp = await fetch(url.toString(), { cache: "no-store" });
  const data = await resp.json();

  const cont = document.getElementById("listaPendientes");

  if (!resp.ok || !data.ok) {
    cont.innerHTML = "<div class='text-danger'>No se pudieron cargar los pendientes.</div>";
    return;
  }

  if (!data.pendientes.length) {
    cont.innerHTML = "<div class='text-muted'>No hay notas pendientes 🎉</div>";
    return;
  }

  cont.innerHTML = data.pendientes
    .map((item) => {
      const sec = escapeHtml(item.seccion);
      const preg = escapeHtml(item.pregunta);
      const n = Number(item.pendientes || 0);
      const ultima = escapeHtml(item.ultima_nota || "-");

      return `
        <div class="border rounded-3 p-3 bg-white">
          <div class="d-flex justify-content-between align-items-start gap-2">
            <div>
              <div class="fw-semibold">Sección: <span class="mono">${sec}</span> · Pregunta: <strong>${preg}</strong></div>
              <div class="text-muted small">Pendientes: <strong>${n}</strong> · Última nota: ${ultima}</div>
            </div>
            <button class="btn btn-sm btn-danger" type="button"
                    onclick="irAPregunta('${sec.replace(/'/g, "\\'")}', '${preg.replace(/'/g, "\\'")}')">
              Ir
            </button>
          </div>
        </div>
      `;
    })
    .join("");
};

PRE.irAPregunta = async function irAPregunta(seccionStr, preguntaNum) {
  const numSeccion = parseInt(String(seccionStr).replace("pagina_", ""), 10);
  if (!numSeccion || numSeccion < 1 || numSeccion > 8) return;

  if (PRE.state.modalPendientes) PRE.state.modalPendientes.hide();

  await new Promise((resolve) => {
    PRE.cargarCuestionario(numSeccion);
    setTimeout(resolve, 250);
  });

  const el = document.querySelector(`.pregunta[data-pregunta="${preguntaNum}"]`);
  if (el) {
    el.scrollIntoView({ behavior: "smooth", block: "start" });
    el.classList.add("highlight-pregunta");
    setTimeout(() => el.classList.remove("highlight-pregunta"), 1800);
  }

  setTimeout(() => PRE.abrirModalNotas(preguntaNum), 400);
};

window.irAPregunta = PRE.irAPregunta;
