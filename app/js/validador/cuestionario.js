window.REV = window.REV || {};

/* =========================================================
   ESTADO GLOBAL
========================================================= */
REV.state = REV.state || {
  seccionActual: 1,
  validadaPorPregunta: {},
  respuestaPorPregunta: {},
  notaContext: {
    pregunta: null,
    seccion: null,
    cid: null,
    folio: null
  },
  modalNotas: null,
  modalPendientes: null
};

/* =========================================================
   HELPERS
========================================================= */
REV.getParams = function () {
  const params = new URLSearchParams(window.location.search);
  return {
    cid: params.get("cid"),
    folio: params.get("folio")
  };
};

REV.escapeHtml = function (str) {
  return String(str ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
};

REV.activarTooltips = function () {
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
    bootstrap.Tooltip.getOrCreateInstance(el);
  });
};

REV.marcarActivo = function (numero) {
  document.querySelectorAll(".nav-link").forEach(a => a.classList.remove("active"));
  document.querySelectorAll(".nav-link")[numero - 1]?.classList.add("active");
};

/* =========================================================
   SESIÓN
========================================================= */
REV.cargarUsuarioSesion = async function () {
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

  const lblUsuario = document.getElementById("lblUsuario");
  if (lblUsuario) {
    lblUsuario.textContent = `${data.user_nombre} ▼`;
  }
};

/* =========================================================
   CARGA DE CUESTIONARIO
========================================================= */
REV.cargarCuestionario = function (numero) {
  REV.state.seccionActual = numero;

  fetch(`pagina_${numero}.html`, { cache: "no-store" })
    .then(r => r.text())
    .then(async html => {
      document.getElementById("contenedor-cuestionario").innerHTML = html;
      REV.marcarActivo(numero);

      await REV.cargarRespuestasSeccion();
      REV.bloquearInputs();
      REV.ocultarElementos();
      REV.convertirInputsAParrafos();

      if (typeof REV.inyectarBotonesNotas === "function") {
        REV.inyectarBotonesNotas();
      }

      await REV.marcarPendientesSecciones();
      await REV.marcarPendientesPreguntas();
      await REV.actualizarTotalPendientes();

      REV.pintarValidacionesEnBotones();
      REV.activarTooltips();
    })
    .catch(err => {
      console.error(err);
      document.getElementById("contenedor-cuestionario").innerHTML =
        "<p>Error al cargar el cuestionario.</p>";
    });
};

window.cargarCuestionario = REV.cargarCuestionario;

/* =========================================================
   RESPUESTAS + EVIDENCIAS
========================================================= */
REV.cargarRespuestasSeccion = async function () {
  const { cid, folio } = REV.getParams();
  const seccion = `pagina_${REV.state.seccionActual}`;

  const url = new URL("../php/obtener_respuestas.php", window.location.href);
  if (cid) url.searchParams.set("cid", cid);
  if (folio) url.searchParams.set("folio", folio);
  url.searchParams.set("seccion", seccion);

  const resp = await fetch(url.toString(), { cache: "no-store" });
  const data = await resp.json();

  if (!resp.ok || !data.ok) {
    console.error("Error al obtener respuestas:", data);
    return;
  }

  REV.state.validadaPorPregunta = {};
  REV.state.respuestaPorPregunta = {};

  const mapa = new Map();
  for (const r of data.respuestas) {
    mapa.set(String(r.pregunta), r);
    REV.state.validadaPorPregunta[String(r.pregunta)] = Number(r.validada || 0);
  }

  const bloques = document.querySelectorAll(".pregunta[data-pregunta]");

  bloques.forEach(b => {
    const num = b.getAttribute("data-pregunta");
    const guardado = mapa.get(String(num));

    const sel = b.querySelector(".respuesta");
    const txt = b.querySelector(".comentarios");

    if (guardado) {
      if (sel) sel.value = guardado.respuesta ?? "";
      if (txt) txt.value = guardado.comentarios ?? "";

      REV.state.respuestaPorPregunta[String(num)] =
        (guardado.respuesta ?? "").toString().trim();
    } else {
      if (sel) sel.value = "";
      if (txt) txt.value = "";

      REV.state.respuestaPorPregunta[String(num)] = "";
      REV.state.validadaPorPregunta[String(num)] = 0;
    }

    let evidencias = [];
    if (guardado && guardado.nom_evidencia) {
      try {
        evidencias = JSON.parse(guardado.nom_evidencia) || [];
      } catch (e) {
        console.warn("No se pudo parsear nom_evidencia en pregunta", num, guardado.nom_evidencia);
        evidencias = [];
      }
    }

    REV.pintarEvidencias(b, evidencias);
  });
};

REV.pintarEvidencias = function (bloquePregunta, evidencias) {
  const cont = bloquePregunta.querySelector(".evidencias-guardadas");
  if (!cont) return;

  if (!Array.isArray(evidencias) || evidencias.length === 0) {
    cont.innerHTML = "<span class='text-muted'>Sin evidencias guardadas.</span>";
    return;
  }

  const items = evidencias.map(ev => {
    const name = REV.escapeHtml(ev.name || "archivo");
    const url = REV.escapeHtml(ev.url || "#");
    return `<li><a href="../${url}" target="_blank" rel="noopener">${name}</a></li>`;
  }).join("");

  cont.innerHTML = `
    <div><strong>Evidencias guardadas:</strong></div>
    <ul class="mb-0">
      ${items}
    </ul>
  `;
};

/* =========================================================
   SOLO LECTURA
========================================================= */
REV.bloquearInputs = function () {
  document.querySelectorAll(
    "select.respuesta, textarea.comentarios, input.evidencia"
  ).forEach(el => el.disabled = true);

  const btnGuardar = document.querySelector("#contenedor-cuestionario #btnGuardar");
  if (btnGuardar) btnGuardar.style.display = "none";
};

REV.ocultarElementos = function () {
  document.querySelectorAll("#contenedor-cuestionario .contDrops")
    .forEach(el => el.style.display = "none");

  document.querySelectorAll("#contenedor-cuestionario .inputEvidencia")
    .forEach(el => el.style.display = "none");

  document.querySelectorAll("#contenedor-cuestionario .labelComentarios")
    .forEach(el => el.style.display = "none");
};

REV.convertirInputsAParrafos = function () {
  document.querySelectorAll(".pregunta[data-pregunta]").forEach(b => {
    const sel = b.querySelector("select.respuesta");
    const txt = b.querySelector("textarea.comentarios");

    const yaExistePSel = b.querySelector(".respuesta-p");
    const yaExistePTxt = b.querySelector(".comentarios-p");

    if (sel && !yaExistePSel) {
      const optionText = sel.selectedOptions?.[0]?.textContent?.trim() || "";
      const pSel = document.createElement("p");
      pSel.className = "respuesta-p form-control-plaintext mb-2";
      pSel.textContent = optionText || "—";
      sel.style.display = "none";
      sel.insertAdjacentElement("afterend", pSel);
    }

    if (txt && !yaExistePTxt) {
      const pTxt = document.createElement("p");
      pTxt.className = "comentarios-p form-control-plaintext";
      pTxt.style.whiteSpace = "pre-wrap";
      pTxt.textContent = (txt.value || "").trim() || "—";
      txt.style.display = "none";
      txt.insertAdjacentElement("afterend", pTxt);
    }
  });
};

/* =========================================================
   BOTONES DE ESTADO VISUAL
========================================================= */
REV.pintarValidacionesEnBotones = function () {
  document.querySelectorAll(".pregunta[data-pregunta]").forEach(b => {
    const p = b.getAttribute("data-pregunta");
    const btn = b.querySelector(".btn-nota");
    if (!btn) return;

    const isValidada = Number(REV.state.validadaPorPregunta[p] || 0) === 1;

    btn.classList.remove("btn-outline-danger", "btn-outline-success", "btn-outline-blue");

    const badgePend = btn.getAttribute("data-badge");
    if (badgePend && Number(badgePend) > 0) {
      btn.classList.add("btn-outline-danger");
      return;
    }

    btn.classList.add(isValidada ? "btn-outline-success" : "btn-outline-blue");
  });
};

/* =========================================================
   VALIDAR RESPUESTA
========================================================= */
REV.configurarBotonValidar = async function () {
  const btn = document.getElementById("btnValidarRespuesta");
  const lbl = document.getElementById("lblMotivoValidacion");
  const txtBtn = document.getElementById("txtBtnValidar");

  if (!btn || !lbl || !txtBtn) return;

  lbl.textContent = "";

  const { cid } = REV.getParams();
  const preg = String(REV.state.notaContext?.pregunta ?? "");
  const sec = String(REV.state.notaContext?.seccion ?? "");

  if (!cid || !preg || !sec) {
    btn.disabled = true;
    lbl.textContent = "Abre una pregunta (📝) para poder validar.";
    txtBtn.textContent = "Validar Respuesta";
    return;
  }

  const isValidada = Number(REV.state.validadaPorPregunta[preg] || 0) === 1;
  if (isValidada) {
    btn.disabled = true;
    lbl.textContent = "Esta pregunta ya está validada.";
    txtBtn.textContent = "✅ Validada";
    return;
  }

  const pendientes = await REV.contarPendientesDePregunta(sec, preg);

  if (pendientes > 0) {
    btn.disabled = true;
    lbl.textContent = `No se puede validar: hay ${pendientes} nota(s) pendiente(s).`;
    txtBtn.textContent = "Hay pendientes";
    return;
  }

  btn.disabled = false;
  txtBtn.textContent = "Validar Respuesta";
};

REV.contarPendientesDePregunta = async function (seccion, pregunta) {
  const { cid } = REV.getParams();
  if (!cid) return 0;

  const url = new URL("../php/pendientes_notas.php", window.location.href);
  url.searchParams.set("cid", cid);
  url.searchParams.set("seccion", seccion);

  const resp = await fetch(url.toString(), { cache: "no-store" });
  const data = await resp.json();

  if (!resp.ok || !data.ok) return 0;

  const mapa = data.pendientes_por_pregunta || {};
  return Number(mapa[String(pregunta)] || 0);
};

REV.validarRespuesta = async function () {
  const btn = document.getElementById("btnValidarRespuesta");
  const sp = document.getElementById("spinValidar");
  const txt = document.getElementById("txtBtnValidar");
  const lbl = document.getElementById("lblMotivoValidacion");

  if (!btn || btn.disabled) return;

  const { cid } = REV.getParams();

  if (!cid || !REV.state.notaContext?.seccion || !REV.state.notaContext?.pregunta) {
    alert("Primero abre una pregunta (📝), luego valida desde el modal.");
    return;
  }

  if (!confirm("¿Confirmas que esta respuesta ya está correcta y no requiere más notas?")) {
    return;
  }

  sp.style.display = "inline-block";
  txt.textContent = "Validando...";
  btn.disabled = true;
  lbl.textContent = "";

  const fd = new FormData();
  fd.append("cid", cid);
  fd.append("seccion", REV.state.notaContext.seccion);
  fd.append("pregunta", REV.state.notaContext.pregunta);

  try {
    const resp = await fetch("../php/validar_respuesta.php", {
      method: "POST",
      body: fd
    });

    const raw = await resp.text();

    let data;
    try {
      data = JSON.parse(raw);
    } catch {
      console.error("No JSON:", raw);
      throw new Error("El servidor no devolvió JSON.");
    }

    if (!resp.ok || !data.ok) {
      throw new Error(data.detalle || data.msg || "No se pudo validar.");
    }

    alert(data.msg || "Respuesta validada ✅");

    REV.state.validadaPorPregunta[String(REV.state.notaContext.pregunta)] = 1;
    REV.pintarValidacionesEnBotones();

    txt.textContent = "✅ Validada";
    lbl.textContent = "Pregunta validada correctamente.";

    await REV.cargarRespuestasSeccion();
    await REV.marcarPendientesPreguntas();

    if (typeof REV.aplicarBloqueoPorValidacion === "function") {
      REV.aplicarBloqueoPorValidacion();
    }
  } catch (e) {
    console.error(e);
    alert(e.message || "Error al validar.");

    await REV.configurarBotonValidar();
    txt.textContent = "Validar Respuesta";
  } finally {
    sp.style.display = "none";
  }
};

/* =========================================================
   PENDIENTES
========================================================= */
REV.marcarPendientesSecciones = async function () {
  const { cid } = REV.getParams();
  if (!cid) return;

  const url = new URL("../php/pendientes_notas.php", window.location.href);
  url.searchParams.set("cid", cid);

  const resp = await fetch(url.toString(), { cache: "no-store" });
  const data = await resp.json();
  if (!resp.ok || !data.ok) return;

  const mapa = data.pendientes_por_seccion || {};

  document.querySelectorAll(".nav-link .badge").forEach(b => b.remove());

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

REV.marcarPendientesPreguntas = async function () {
  const { cid } = REV.getParams();
  if (!cid) return;

  const seccion = `pagina_${REV.state.seccionActual}`;
  const url = new URL("../php/pendientes_notas.php", window.location.href);
  url.searchParams.set("cid", cid);
  url.searchParams.set("seccion", seccion);

  const resp = await fetch(url.toString(), { cache: "no-store" });
  const data = await resp.json();
  if (!resp.ok || !data.ok) return;

  const mapa = data.pendientes_por_pregunta || {};

  document.querySelectorAll(".pregunta[data-pregunta]").forEach(b => {
    const p = b.getAttribute("data-pregunta");
    const btn = b.querySelector(".btn-nota");
    if (!btn) return;

    const pendientes = Number(mapa[p] || 0);

    btn.classList.remove("btn-outline-danger", "btn-outline-success", "btn-outline-blue");
    btn.removeAttribute("data-badge");

    if (pendientes > 0) {
      btn.classList.add("btn-outline-danger");
      btn.setAttribute("data-badge", String(pendientes));
      return;
    }

    const isValidada = Number(REV.state.validadaPorPregunta[p] || 0) === 1;
    btn.classList.add(isValidada ? "btn-outline-success" : "btn-outline-blue");
  });
};

REV.actualizarTotalPendientes = async function () {
  const { cid } = REV.getParams();
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

REV.abrirPendientes = async function () {
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

REV.cargarListaPendientes = async function () {
  const { cid } = REV.getParams();

  const url = new URL("../php/listar_pendientes.php", window.location.href);
  url.searchParams.set("cid", cid);

  const resp = await fetch(url.toString(), { cache: "no-store" });
  const data = await resp.json();

  const cont = document.getElementById("listaPendientes");
  if (!cont) return;

  if (!resp.ok || !data.ok) {
    cont.innerHTML = "<div class='text-danger'>No se pudieron cargar los pendientes.</div>";
    return;
  }

  if (!data.pendientes.length) {
    cont.innerHTML = "<div class='text-muted'>No hay notas pendientes 🎉</div>";
    return;
  }

  cont.innerHTML = data.pendientes.map(item => {
    const sec = REV.escapeHtml(item.seccion);
    const preg = REV.escapeHtml(item.pregunta);
    const n = Number(item.pendientes || 0);
    const ultima = REV.escapeHtml(item.ultima_nota || "-");

    return `
      <div class="border rounded-3 p-3 bg-white">
        <div class="d-flex justify-content-between align-items-start gap-2">
          <div>
            <div class="fw-semibold">Sección: <span class="mono">${sec}</span> · Pregunta: <strong>${preg}</strong></div>
            <div class="text-muted small">Pendientes: <strong>${n}</strong> · Última nota: ${ultima}</div>
          </div>
          <button class="btn btn-sm btn-danger" type="button"
                  onclick="REV.irAPregunta('${sec.replace(/'/g, "\\'")}', '${preg.replace(/'/g, "\\'")}')">
            Ir
          </button>
        </div>
      </div>
    `;
  }).join("");
};

REV.irAPregunta = async function (seccionStr, preguntaNum) {
  const numSeccion = parseInt(String(seccionStr).replace("pagina_", ""), 10);
  if (!numSeccion || numSeccion < 1 || numSeccion > 8) return;

  if (REV.state.modalPendientes) REV.state.modalPendientes.hide();

  await new Promise(resolve => {
    REV.cargarCuestionario(numSeccion);
    setTimeout(resolve, 250);
  });

  const el = document.querySelector(`.pregunta[data-pregunta="${preguntaNum}"]`);
  if (el) {
    el.scrollIntoView({ behavior: "smooth", block: "start" });
    el.classList.add("highlight-pregunta");
    setTimeout(() => el.classList.remove("highlight-pregunta"), 1800);
  }

  setTimeout(() => {
    if (typeof REV.abrirModalNotas === "function") {
      REV.abrirModalNotas(preguntaNum);
    }
  }, 400);
};

window.irAPregunta = REV.irAPregunta;

REV.refrescarModalPendientesSiEstaAbierto = async function () {
  const el = document.getElementById("modalPendientes");
  if (!el) return;

  const visible = el.classList.contains("show");
  if (visible) await REV.cargarListaPendientes();
};

/* =========================================================
   EVENTOS
========================================================= */
document.addEventListener("DOMContentLoaded", async () => {
  REV.activarTooltips();
  await REV.cargarUsuarioSesion();
  REV.cargarCuestionario(1);

  const btnValidar = document.getElementById("btnValidarRespuesta");
  if (btnValidar) {
    btnValidar.onclick = REV.validarRespuesta;
  } else {
    console.warn("No existe #btnValidarRespuesta en el DOM.");
  }
});