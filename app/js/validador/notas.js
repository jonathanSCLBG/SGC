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
    btn.title = "Notas del validador";

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

  const txtNuevaNota = document.getElementById("txtNuevaNota");
  if (txtNuevaNota) txtNuevaNota.value = "";

  const historial = document.getElementById("historialNotas");
  if (historial) historial.innerHTML = "<div class='text-muted'>Cargando...</div>";

  if (!REV.state.modalNotas) {
    REV.state.modalNotas = new bootstrap.Modal(document.getElementById("modalNotas"));
  }

  REV.state.modalNotas.show();

  REV.engancharGuardarNota();

  bootstrap.Tab.getOrCreateInstance(document.getElementById("tab-historial")).show();

  await REV.cargarHistorialNotas();
  await REV.configurarBotonValidar();
  REV.aplicarBloqueoPorValidacion();
};

window.abrirModalNotas = REV.abrirModalNotas;

/* ============================================================
   Bloqueo por validación
   OJO:
   - El validador SÍ puede crear notas nuevas aunque la pregunta esté validada
   - Solo se bloquea el botón de validar respuesta si ya está validada
============================================================ */
REV.aplicarBloqueoPorValidacion = function () {
  const p = String(REV.state.notaContext?.pregunta || "");
  const isValidada = Number(REV.state.validadaPorPregunta[p] || 0) === 1;

  const tabNueva = document.getElementById("tab-nueva");
  const paneNueva = document.getElementById("pane-nueva");
  const txtNueva = document.getElementById("txtNuevaNota");
  const btnGuardarNota = document.getElementById("btnGuardarNota");
  const btnValidar = document.getElementById("btnValidarRespuesta");

  if (tabNueva) {
    tabNueva.classList.remove("disabled");
    tabNueva.removeAttribute("aria-disabled");
    tabNueva.removeAttribute("tabindex");
  }

  if (paneNueva) {
    paneNueva.classList.remove("nota-bloqueada");
  }

  if (txtNueva) txtNueva.disabled = false;
  if (btnGuardarNota) btnGuardarNota.disabled = false;

  // El único que sí se bloquea al estar validada es el botón de validar respuesta
  if (btnValidar) {
    btnValidar.disabled = isValidada;
  }
};

/* ============================================================
   Cargar historial de notas
   OJO:
   - En el validador NO se muestran botones de acción
   - Solo se muestra el badge de estado
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

  cont.innerHTML = data.notas.map((n) => {
    const badge = Number(n.resuelta) === 1
      ? "<span class='badge bg-success'>Resuelta</span>"
      : "<span class='badge bg-warning text-dark'>Pendiente</span>";

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
          </div>
        </div>
      </div>
    `;
  }).join("");
};

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

    console.count("guardarNota");

    const resp = await fetch("../php/enviar_nota_validador.php", {
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
    REV.aplicarBloqueoPorValidacion();
  }
};

/* ============================================================
   Toggle nota
   Se deja por compatibilidad, pero el validador ya no tendrá
   botones visibles para cambiar estado desde el historial
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