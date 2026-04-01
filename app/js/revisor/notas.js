window.REV = window.REV || {};

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
  modalPendientes: null,
  guardandoNota: false
};

/* ============================================================
   Inyectar botón 📝 por pregunta
============================================================ */
REV.inyectarBotonesNotas = function () {
  document.querySelectorAll(".pregunta[data-pregunta]").forEach((b) => {
    if (b.querySelector(".btn-nota")) return;

    b.style.position = "relative";

    const btn = document.createElement("button");
    btn.type = "button";
    btn.className = "btn btn-sm btn-outline-blue btn-nota";
    btn.textContent = "📝";
    btn.title = "Notas del revisor";

    const preguntaNum = b.getAttribute("data-pregunta");
    btn.addEventListener("click", () => REV.abrirModalNotas(preguntaNum));

    b.appendChild(btn);
  });
};

/* ============================================================
   Abrir modal de notas
============================================================ */
REV.abrirModalNotas = async function (preguntaNum) {
  const { cid, folio } = REV.getParams();

  if (!cid && !folio) {
    alert("Falta cid/folio en la URL.");
    return;
  }

  REV.state.notaContext = {
    pregunta: String(preguntaNum),
    seccion: `pagina_${REV.state.seccionActual}`,
    cid,
    folio
  };

  const meta = document.getElementById("modalNotasMeta");
  if (meta) {
    meta.textContent =
      `Cuestionario: ${folio || ("CID " + cid)} · Sección: ${REV.state.notaContext.seccion} · Pregunta: ${REV.state.notaContext.pregunta}`;
  }

  const txt = document.getElementById("txtNuevaNota");
  if (txt) txt.value = "";

  const cont = document.getElementById("historialNotas");
  if (cont) cont.innerHTML = "<div class='text-muted'>Cargando...</div>";

  if (!REV.state.modalNotas) {
    REV.state.modalNotas = new bootstrap.Modal(document.getElementById("modalNotas"));
  }

  REV.state.modalNotas.show();

  bootstrap.Tab.getOrCreateInstance(document.getElementById("tab-historial")).show();

  REV.engancharGuardarNota();

  await REV.cargarHistorialNotas();
  await REV.configurarBotonValidar?.();
  REV.aplicarBloqueoPorValidacion();
};

window.abrirModalNotas = REV.abrirModalNotas;

/* ============================================================
   Bloqueo por validación
============================================================ */
REV.aplicarBloqueoPorValidacion = function () {
  const p = String(REV.state.notaContext?.pregunta || "");
  const isValidada = Number(REV.state.validadaPorPregunta[p] || 0) === 1;

  const tabNueva = document.getElementById("tab-nueva");
  const paneNueva = document.getElementById("pane-nueva");
  const txtNueva = document.getElementById("txtNuevaNota");
  const btnGuardarNota = document.getElementById("btnGuardarNota");
  const btnValidar = document.getElementById("btnValidarRespuesta");

  if (!tabNueva || !paneNueva || !txtNueva || !btnGuardarNota || !btnValidar) return;

  if (isValidada) {
    tabNueva.classList.add("disabled");
    tabNueva.setAttribute("aria-disabled", "true");
    tabNueva.setAttribute("tabindex", "-1");

    paneNueva.classList.add("nota-bloqueada");
    txtNueva.disabled = true;
    btnGuardarNota.disabled = true;

    btnValidar.disabled = false;
    btnValidar.classList.remove("btn-outline-success", "btn-success");
    btnValidar.classList.add("btn-outline-secondary");
    btnValidar.textContent = "Desvalidar Respuesta";
  } else {
    tabNueva.classList.remove("disabled");
    tabNueva.removeAttribute("aria-disabled");
    tabNueva.removeAttribute("tabindex");

    paneNueva.classList.remove("nota-bloqueada");
    txtNueva.disabled = false;
    btnGuardarNota.disabled = false;

    btnValidar.disabled = false;
    btnValidar.classList.remove("btn-outline-secondary", "btn-success");
    btnValidar.classList.add("btn-outline-success");
    btnValidar.textContent = "Validar Respuesta";
  }
};

/* ============================================================
   Cargar historial de notas
============================================================ */
REV.cargarHistorialNotas = async function () {
  const ctx = REV.state.notaContext;

  const url = new URL("../php/obtener_notas.php", window.location.href);
  url.searchParams.set("cid", ctx.cid);
  url.searchParams.set("seccion", ctx.seccion);
  url.searchParams.set("pregunta", ctx.pregunta);

  const resp = await fetch(url.toString(), { cache: "no-store" });
  const data = await resp.json();

  const cont = document.getElementById("historialNotas");
  if (!cont) return;

  if (!resp.ok || !data.ok) {
    console.error(data);
    cont.innerHTML = "<div class='text-danger'>No se pudieron cargar las notas.</div>";
    return;
  }

  if (!data.notas.length) {
    cont.innerHTML = "<div class='text-muted'>Sin notas aún.</div>";
    return;
  }

  const p = String(ctx?.pregunta || "");
  const isValidadaPregunta = Number(REV.state.validadaPorPregunta?.[p] || 0) === 1;

  cont.innerHTML = data.notas.map((n) => {
    const badge = Number(n.resuelta) === 1
      ? "<span class='badge bg-success'>Resuelta</span>"
      : "<span class='badge bg-warning text-dark'>Pendiente</span>";

    let acciones = "";
    if (!isValidadaPregunta) {
      acciones = Number(n.resuelta) === 1
        ? `<button class="btn btn-sm btn-outline-secondary" onclick="REV.toggleNota(${n.id}, 0)">Marcar pendiente</button>`
        : `<button class="btn btn-sm btn-outline-success" onclick="REV.toggleNota(${n.id}, 1)">Marcar resuelta</button>`;
    } else {
      acciones = `
        <button class="btn btn-sm btn-outline-secondary" type="button" disabled
                title="Pregunta validada: no se pueden cambiar estados">
          Bloqueado
        </button>
      `;
    }

    const resueltaMeta = Number(n.resuelta) === 1
      ? `<div class="text-muted small mt-1">Resuelta por <strong>${REV.escapeHtml(n.resuelta_por || "-")}</strong> · ${REV.escapeHtml(n.resuelta_en || "-")}</div>`
      : "";

    return `
      <div class="border rounded-3 p-3 bg-white">
        <div class="d-flex align-items-start justify-content-between gap-2">
          <div>
            <div class="small text-muted">
              <strong>${REV.escapeHtml(n.revisor_nombre || "")}</strong> · ${REV.escapeHtml(n.creado_en || "")}
            </div>
            <div class="mt-1">${REV.escapeHtml(n.nota || "")}</div>
            ${resueltaMeta}
          </div>
          <div class="d-flex flex-column gap-2 align-items-end">
            ${badge}
            ${acciones}
          </div>
        </div>
      </div>
    `;
  }).join("");
};

/* ============================================================
   Toggle nota
============================================================ */
REV.toggleNota = async function (id, resuelta) {
  const fd = new FormData();
  fd.append("id", String(id));
  fd.append("resuelta", String(resuelta));

  const resp = await fetch("../php/toggle_nota.php", {
    method: "POST",
    body: fd
  });
  const data = await resp.json();

  if (!resp.ok || !data.ok) {
    console.error(data);
    alert(data.msg || "No se pudo actualizar el estado.");
    return;
  }

  await REV.cargarHistorialNotas();
  await REV.marcarPendientesSecciones?.();
  await REV.marcarPendientesPreguntas?.();
  await REV.actualizarTotalPendientes?.();
  await REV.refrescarModalPendientesSiEstaAbierto?.();
  await REV.configurarBotonValidar?.();
};

window.toggleNota = REV.toggleNota;

/* ============================================================
   Guardar nota
============================================================ */
REV.guardarNota = async function (e) {
  if (e) e.preventDefault();

  if (REV.state.guardandoNota) return;

  const ctx = REV.state?.notaContext;
  if (!ctx?.cid || !ctx?.seccion || !ctx?.pregunta) {
    alert("Contexto inválido (cid/seccion/pregunta).");
    console.error("notaContext:", ctx);
    return;
  }

  const txt = document.getElementById("txtNuevaNota");
  const nota = (txt?.value ?? "").trim();

  if (!nota) {
    alert("Escribe una nota antes de guardar.");
    return;
  }

  const btn = document.getElementById("btnGuardarNota");
  REV.state.guardandoNota = true;

  if (btn) btn.disabled = true;

  try {
    const fd = new FormData();
    fd.append("cid", String(ctx.cid));
    fd.append("seccion", ctx.seccion);
    fd.append("pregunta", String(ctx.pregunta));
    fd.append("nota", nota);

    console.count("guardarNota revisor");

    const resp = await fetch("../php/enviar_nota.php", {
      method: "POST",
      body: fd
    });

    const raw = await resp.text();
    console.log("RAW guardar_nota:", raw);

    let data;
    try {
      data = JSON.parse(raw);
    } catch {
      alert("El servidor no devolvió JSON. Revisa consola.");
      return;
    }

    if (!resp.ok || !data.ok) {
      console.error(data);
      alert(data.msg || "Error al guardar nota.");
      return;
    }

    if (txt) txt.value = "";

    await REV.cargarHistorialNotas();
    await REV.marcarPendientesSecciones?.();
    await REV.marcarPendientesPreguntas?.();
    await REV.actualizarTotalPendientes?.();
    await REV.refrescarModalPendientesSiEstaAbierto?.();
    await REV.configurarBotonValidar?.();

    bootstrap.Tab.getOrCreateInstance(document.getElementById("tab-historial")).show();

    alert("✅ Nota guardada.\n\n" + (data.email_msg || ""));
  } catch (e2) {
    console.error(e2);
    alert("Error de red/servidor al guardar nota.");
  } finally {
    REV.state.guardandoNota = false;
    if (btn) btn.disabled = false;
    REV.aplicarBloqueoPorValidacion();
  }
};

/* ============================================================
   Enganchar botón Guardar nota SOLO UNA VEZ
============================================================ */
REV.engancharGuardarNota = function () {
  const btn = document.getElementById("btnGuardarNota");
  if (!btn) {
    console.warn("No existe #btnGuardarNota en el DOM.");
    return;
  }

  btn.type = "button";
  btn.onclick = REV.guardarNota;
};

/* ============================================================
   Arranque
============================================================ */
document.addEventListener("DOMContentLoaded", () => {
  REV.engancharGuardarNota();

  const formNota = document.getElementById("formNota");
  if (formNota) {
    formNota.addEventListener("submit", (e) => {
      e.preventDefault();
    });
  }
});