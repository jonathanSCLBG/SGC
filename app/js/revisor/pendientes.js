window.REV = window.REV || {};

/* ============================================================
   Activar / reinicializar tooltips Bootstrap
   ============================================================ */
REV.activarTooltips = function activarTooltips() {
  const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');

  tooltipTriggerList.forEach((el) => {
    const actual = bootstrap.Tooltip.getInstance(el);
    if (actual) actual.dispose();
    new bootstrap.Tooltip(el);
  });
};

REV.marcarPendientesSecciones = async function marcarPendientesSecciones() {
  const { cid } = REV.getParams();
  if (!cid) return;

  const url = new URL("../php/pendientes_notas.php", window.location.href);
  url.searchParams.set("cid", cid);

  const resp = await fetch(url.toString(), { cache: "no-store" });
  const data = await resp.json();

  if (!resp.ok || !data.ok) {
    console.error("Error pendientes secciones:", data);
    return;
  }

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
      badge.setAttribute("data-bs-toggle", "tooltip");
      badge.setAttribute("data-bs-placement", "top");
      badge.setAttribute("title", `${pendientes} pendiente(s) en esta sección`);

      tab.appendChild(badge);
    }
  }

  REV.activarTooltips();
};

REV.marcarPendientesPreguntas = async function marcarPendientesPreguntas() {
  const { cid } = REV.getParams();
  if (!cid) return;

  const seccion = `pagina_${REV.state.seccionActual}`;
  const url = new URL("../php/pendientes_notas.php", window.location.href);
  url.searchParams.set("cid", cid);
  url.searchParams.set("seccion", seccion);

  const resp = await fetch(url.toString(), { cache: "no-store" });
  const data = await resp.json();

  if (!resp.ok || !data.ok) {
    console.error("Error pendientes preguntas:", data);
    return;
  }

  const mapaPend = data.pendientes_por_pregunta || {};

  document.querySelectorAll(".pregunta[data-pregunta]").forEach((b) => {
    const p = String(b.getAttribute("data-pregunta"));
    const btn = b.querySelector(".btn-nota");
    if (!btn) return;

    const pendientes = Number(mapaPend[p] || 0);
    const isValidada = Number(REV.state.validadaPorPregunta[p] || 0) === 1;

    btn.classList.remove("btn-outline-danger", "btn-outline-success", "btn-outline-blue");
    btn.removeAttribute("data-badge");
    btn.removeAttribute("data-badge-ok");
    btn.removeAttribute("data-bs-toggle");
    btn.removeAttribute("data-bs-placement");
    btn.removeAttribute("title");

    if (pendientes > 0) {
      btn.classList.add("btn-outline-danger");
      btn.setAttribute("data-badge", String(pendientes));
      btn.setAttribute("data-bs-toggle", "tooltip");
      btn.setAttribute("data-bs-placement", "left");
      btn.setAttribute("title", `${pendientes} nota(s) pendiente(s) por resolver`);
      return;
    }

    if (isValidada) {
      btn.classList.add("btn-outline-success");
      btn.setAttribute("data-badge-ok", "✓");
      btn.setAttribute("data-bs-toggle", "tooltip");
      btn.setAttribute("data-bs-placement", "left");
      btn.setAttribute("title", "Pregunta validada");
      return;
    }

    btn.classList.add("btn-outline-blue");
    btn.setAttribute("data-bs-toggle", "tooltip");
    btn.setAttribute("data-bs-placement", "left");
    btn.setAttribute("title", "Sin notas pendientes");
  });

  REV.activarTooltips();
};

REV.actualizarTotalPendientes = async function actualizarTotalPendientes() {
  const { cid } = REV.getParams();
  if (!cid) return;

  const url = new URL("../php/pendientes_notas.php", window.location.href);
  url.searchParams.set("cid", cid);

  const resp = await fetch(url.toString(), { cache: "no-store" });
  const data = await resp.json();

  const badge = document.getElementById("badgeTotalPendientes");
  if (!badge) return;

  if (!resp.ok || !data.ok) {
    console.error("Error total pendientes:", data);
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

REV.abrirPendientes = async function abrirPendientes() {
  const { cid } = REV.getParams();
  if (!cid) { 
    alert("Falta cid en la URL."); 
    return; 
  }

  if (!REV.state.modalPendientes) {
    REV.state.modalPendientes = new bootstrap.Modal(document.getElementById("modalPendientes"));
  }

  document.getElementById("listaPendientes").innerHTML = "<div class='text-muted'>Cargando...</div>";
  REV.state.modalPendientes.show();

  await REV.cargarListaPendientes();
};

window.abrirPendientes = REV.abrirPendientes;

REV.cargarListaPendientes = async function cargarListaPendientes() {
  const { cid } = REV.getParams();
  const url = new URL("../php/listar_pendientes.php", window.location.href);
  url.searchParams.set("cid", cid);

  const resp = await fetch(url.toString(), { cache: "no-store" });
  const data = await resp.json();

  const cont = document.getElementById("listaPendientes");

  if (!resp.ok || !data.ok) {
    console.error(data);
    cont.innerHTML = "<div class='text-danger'>No se pudieron cargar los pendientes.</div>";
    return;
  }

  if (!data.pendientes.length) {
    cont.innerHTML = "<div class='text-muted'>No hay notas pendientes 🎉</div>";
    return;
  }

  cont.innerHTML = data.pendientes.map((item) => {
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
  }).join("");
};

REV.irAPregunta = async function irAPregunta(seccionStr, preguntaNum) {
  const numSeccion = parseInt(String(seccionStr).replace("pagina_", ""), 10);
  if (!numSeccion || numSeccion < 1 || numSeccion > 8) return;

  if (REV.state.modalPendientes) REV.state.modalPendientes.hide();

  await REV.cargarCuestionario(numSeccion);
  await sleep(250);

  const el = document.querySelector(`.pregunta[data-pregunta="${preguntaNum}"]`);
  if (el) {
    el.scrollIntoView({ behavior: "smooth", block: "start" });
    el.classList.add("highlight-pregunta");
    setTimeout(() => el.classList.remove("highlight-pregunta"), 1800);
  }

  setTimeout(() => REV.abrirModalNotas(preguntaNum), 400);
};

window.irAPregunta = REV.irAPregunta;

REV.refrescarModalPendientesSiEstaAbierto = async function refrescarModalPendientesSiEstaAbierto() {
  const el = document.getElementById("modalPendientes");
  if (!el) return;
  const visible = el.classList.contains("show");
  if (visible) await REV.cargarListaPendientes();
};