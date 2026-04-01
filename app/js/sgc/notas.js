window.PRE = window.PRE || {};

/* ============================================================
   Botón 📝 por pregunta
   ============================================================ */
PRE.inyectarBotonesNotas = function inyectarBotonesNotas() {
  document.querySelectorAll(".pregunta[data-pregunta]").forEach((b) => {
    if (b.querySelector(".btn-nota")) return;

    b.style.position = "relative";

    const btn = document.createElement("button");
    btn.type = "button";
    btn.className = "btn btn-sm btn-outline-blue btn-nota";
    btn.textContent = "📝";
    btn.title = "Ver notas del revisor";

    const preguntaNum = b.getAttribute("data-pregunta");
    btn.addEventListener("click", () => PRE.abrirModalNotas(preguntaNum));

    b.appendChild(btn);
  });
};

/* ============================================================
   Ocultar párrafo extra de comentarios
   ============================================================ */
PRE.ocultarParrafoComentarios = function ocultarParrafoComentarios() {
  document.querySelectorAll("#contenedor-cuestionario .parrafoComentarios").forEach((el) => {
    el.style.display = "none";
  });
};

/* ============================================================
   Abrir modal de notas
   ============================================================ */
PRE.abrirModalNotas = async function abrirModalNotas(preguntaNum) {
  const { cid, folio } = PRE.getParams();

  if (!cid && !folio) {
    alert("Falta cid o folio en la URL.");
    return;
  }

  PRE.state.notaContext = {
    pregunta: String(preguntaNum),
    seccion: `pagina_${PRE.state.seccionActual}`,
    cid,
    folio,
  };

  const meta = document.getElementById("modalNotasMeta");
  if (meta) {
    meta.textContent =
      `Cuestionario: ${folio || ("CID " + cid)} · Sección: ${PRE.state.notaContext.seccion} · Pregunta: ${PRE.state.notaContext.pregunta}`;
  }

  const historial = document.getElementById("historialNotas");
  if (historial) {
    historial.innerHTML = "<div class='text-muted'>Cargando...</div>";
  }

  if (!PRE.state.modalNotas) {
    PRE.state.modalNotas = new bootstrap.Modal(document.getElementById("modalNotas"));
  }

  PRE.state.modalNotas.show();

  await PRE.cargarHistorialNotasSoloLectura();
  PRE.inyectarBotonCorregirYAvisarEnModal();
};

window.abrirModalNotas = PRE.abrirModalNotas;

/* ============================================================
   Cargar historial de notas
   ============================================================ */
PRE.cargarHistorialNotasSoloLectura = async function cargarHistorialNotasSoloLectura() {
  const ctx = PRE.state.notaContext;
  const cont = document.getElementById("historialNotas");

  if (!ctx || !cont) return;

  try {
    const url = new URL("../php/obtener_notas.php", window.location.href);

    if (ctx.cid) url.searchParams.set("cid", ctx.cid);
    if (ctx.folio) url.searchParams.set("folio", ctx.folio);
    url.searchParams.set("seccion", ctx.seccion);
    url.searchParams.set("pregunta", ctx.pregunta);

    const resp = await fetch(url.toString(), { cache: "no-store" });
    const raw = await resp.text();

    let data;
    try {
      data = JSON.parse(raw);
    } catch {
      console.error("obtener_notas.php no devolvió JSON:", raw);
      cont.innerHTML = "<div class='text-danger'>Respuesta inválida del servidor al cargar notas.</div>";
      return;
    }

    if (!resp.ok || !data.ok) {
      console.error("Error obtener_notas.php:", data);
      cont.innerHTML = `<div class='text-danger'>${escapeHtml(data.msg || "No se pudieron cargar las notas.")}</div>`;
      return;
    }

    if (!Array.isArray(data.notas) || !data.notas.length) {
      cont.innerHTML = "<div class='text-muted'>Sin notas aún.</div>";
      return;
    }

    cont.innerHTML = data.notas.map((n) => {
      const idNota = Number(n.id || 0);
      const esResuelta = Number(n.resuelta || 0) === 1;

      const badge = esResuelta
        ? "<span class='badge bg-success'>Resuelta</span>"
        : "<span class='badge bg-warning text-dark'>Pendiente</span>";

      const corregidaMeta = n.corregida_por
        ? `<div class="text-muted small mt-1">Corregida por <strong>${escapeHtml(n.corregida_por || "-")}</strong> · ${escapeHtml(n.corregida_en || "-")}</div>`
        : "";

      const resueltaMeta = esResuelta
        ? `<div class="text-muted small mt-1">Resuelta por <strong>${escapeHtml(n.resuelta_por || "-")}</strong> · ${escapeHtml(n.resuelta_en || "-")}</div>`
        : "";

      return `
        <div class="border rounded-3 p-3 bg-white nota-item"
             data-id="${idNota}"
             data-resuelta="${esResuelta ? 1 : 0}">
          <div class="d-flex align-items-start justify-content-between gap-2">
            <div>
              <div class="small text-muted">
                <strong>${escapeHtml(n.revisor_nombre || "")}</strong> · ${escapeHtml(n.creado_en || "")}
              </div>
              <div class="mt-1">${escapeHtml(n.nota || "")}</div>
              ${corregidaMeta}
              ${resueltaMeta}
            </div>
            <div class="d-flex flex-column gap-2 align-items-end">
              ${badge}
            </div>
          </div>
        </div>
      `;
    }).join("");

  } catch (err) {
    console.error(err);
    cont.innerHTML = "<div class='text-danger'>Error de red/servidor al cargar notas.</div>";
  }
};

/* ============================================================
   Botón Corregir y avisar
   1) Guarda solo la pregunta
   2) Registra corregida_por / corregida_en
   3) Envía correo al revisor
   4) NO cambia resuelta
   ============================================================ */
PRE.inyectarBotonCorregirYAvisarEnModal = function inyectarBotonCorregirYAvisarEnModal() {
  const modalEl = document.getElementById("modalNotas");
  if (!modalEl) return;

  let footer = modalEl.querySelector(".modal-footer");
  if (!footer) {
    footer = document.createElement("div");
    footer.className = "modal-footer";
    modalEl.querySelector(".modal-content")?.appendChild(footer);
  }

  let btn = footer.querySelector("#btnCorregirAvisar");
  if (!btn) {
    btn = document.createElement("button");
    btn.type = "button";
    btn.id = "btnCorregirAvisar";
    btn.className = "btn btn-success";
    btn.textContent = "Corregir y avisar";
    footer.prepend(btn);
  }

  btn.onclick = async () => {
    const p = PRE.state?.notaContext?.pregunta;

    try {
      btn.disabled = true;
      btn.textContent = "Guardando...";

      // 1) Guardar solo la pregunta actual
      await PRE.guardarPregunta(p);

      // 2) Buscar notas pendientes
      const notasPendientes = Array.from(
        document.querySelectorAll('#historialNotas .nota-item[data-resuelta="0"]')
      );

      if (!notasPendientes.length) {
        alert("Esta pregunta no tiene notas pendientes.");
        return;
      }

      let procesadas = 0;
      let enviadas = 0;
      let fallos = 0;
      const mensajesError = [];

      for (const notaEl of notasPendientes) {
        const notaId = Number(notaEl.dataset.id || 0);

        if (!notaId) {
          fallos++;
          mensajesError.push("Nota sin id válido.");
          continue;
        }

        const params = new URLSearchParams();
        params.append("id", String(notaId));

        const resp = await fetch("../php/avisar_correccion_nota.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
          },
          body: params.toString()
        });

        const raw = await resp.text();
        let data;

        try {
          data = JSON.parse(raw);
        } catch {
          console.error("avisar_correccion_nota.php no devolvió JSON:", raw);
          fallos++;
          mensajesError.push(`Nota ${notaId}: respuesta no válida del servidor.`);
          continue;
        }

        procesadas++;

        if (!resp.ok || !data.ok) {
          console.error("avisar_correccion_nota.php error:", data);
          fallos++;
          mensajesError.push(`Nota ${notaId}: ${data.detalle || data.msg || "Error desconocido"}`);
          continue;
        }

        enviadas++;
      }

      // 3) Recargar historial
      await PRE.cargarHistorialNotasSoloLectura();
      await PRE.marcarPendientesSecciones?.();
      await PRE.marcarPendientesPreguntas?.();
      await PRE.actualizarTotalPendientes?.();

      let resumen =
        `Listo ✅\n\n` +
        `Notas pendientes procesadas: ${procesadas}\n` +
        `Correos enviados: ${enviadas}\n` +
        `Fallos: ${fallos}\n\n` +
        `La nota sigue pendiente hasta que el revisor la valide.`;

      if (mensajesError.length) {
        resumen += `\n\n${mensajesError.join("\n")}`;
      }

      alert(resumen);

    } catch (err) {
      console.error(err);
      alert(err.message || "Error al corregir y avisar.");
    } finally {
      btn.disabled = false;
      btn.textContent = "Corregir y avisar";
    }
  };
};