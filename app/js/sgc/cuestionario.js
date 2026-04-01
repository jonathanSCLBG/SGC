window.PRE = window.PRE || {};

/* ============================================================
   Tabs activo
   ============================================================ */
PRE.marcarActivo = function marcarActivo(numero) {
  document.querySelectorAll(".nav-link").forEach((a) => a.classList.remove("active"));
  document.querySelectorAll(".nav-link")[numero - 1]?.classList.add("active");
};

/* ============================================================
   Cargar sección pagina_X.html
   ============================================================ */
PRE.cargarCuestionario = function cargarCuestionario(numero) {
  PRE.state.seccionActual = numero;

  fetch(`pagina_${numero}.html`, { cache: "no-store" })
    .then((r) => r.text())
    .then(async (html) => {
      document.getElementById("contenedor-cuestionario").innerHTML = html;

      PRE.marcarActivo(numero);
      PRE.engancharGuardar();

      // Precargar respuestas/evidencias
      await PRE.cargarRespuestasSeccion();

      // Botones notas + ocultar texto extra
      PRE.inyectarBotonesNotas();
      PRE.ocultarParrafoComentarios();

      // Pendientes
      await PRE.marcarPendientesSecciones?.();
      await PRE.marcarPendientesPreguntas?.();
      await PRE.actualizarTotalPendientes?.();
    })
    .catch((err) => {
      console.error(err);
      document.getElementById("contenedor-cuestionario").innerHTML =
        "<p>Error al cargar el cuestionario.</p>";
    });
};

// compatibilidad con onclick del HTML
window.cargarCuestionario = PRE.cargarCuestionario;

/* ============================================================
   Conectar botón Guardar (por sección)
   ============================================================ */
PRE.engancharGuardar = function engancharGuardar() {
  const btn = document.querySelector("#contenedor-cuestionario #btnGuardar");
  if (!btn) return;
  btn.onclick = () => PRE.guardarSeccion();
};

/* ============================================================
   Guardar TODA la sección
   ============================================================ */
PRE.guardarSeccion = async function guardarSeccion() {
  const form = document.getElementById("form-cuestionario");
  if (!form) return alert("No se encontró el formulario (#form-cuestionario).");

  const preguntas = form.querySelectorAll(".pregunta[data-pregunta]");
  if (!preguntas.length) return alert("No se encontraron preguntas en esta sección.");

  const { cid, folio } = PRE.getParams();
  if (!cid && !folio) return alert("Falta cid o folio en la URL.");

  const fd = new FormData();
  if (cid) fd.append("cid", cid);
  if (folio) fd.append("folio", folio);
  fd.append("seccion", `pagina_${PRE.state.seccionActual}`);
  fd.append("enviar_correo", "1");

  preguntas.forEach((b) => {
    const num = b.getAttribute("data-pregunta");

    const r = b.querySelector(".respuesta")?.value ?? "";
    const c = b.querySelector(".comentarios")?.value ?? "";

    fd.append("pregunta[]", num);
    fd.append("respuesta[]", r);
    fd.append("comentarios[]", c);

    const input = b.querySelector(".evidencia");
    if (input?.files?.length) {
      for (const file of input.files) {
        fd.append(`evidencia_${num}[]`, file);
      }
    }
  });

  try {
    const resp = await fetch("../php/guardar_respuestas.php", { method: "POST", body: fd });

    const raw = await resp.text();
    let data;
    try {
      data = JSON.parse(raw);
    } catch {
      console.error("No JSON:", raw);
      alert("El servidor no devolvió JSON. Revisa consola.");
      return;
    }

    if (!resp.ok || !data.ok) {
      console.error(data);
      alert(data.msg || "Error al guardar.");
      return;
    }

    alert((data.msg || "Guardado ✅") + "\n\n" + (data.email_msg || ""));

    await PRE.cargarRespuestasSeccion();
    await PRE.marcarPendientesSecciones?.();
    await PRE.marcarPendientesPreguntas?.();
    await PRE.actualizarTotalPendientes?.();
  } catch (e) {
    console.error(e);
    alert("Error de red/servidor al guardar.");
  }
};
/* ============================================================
   Guardar SOLO una pregunta
   ============================================================ */
PRE.guardarPregunta = async function guardarPregunta(preguntaNum) {
  const form = document.getElementById("form-cuestionario");
  const bloque = form?.querySelector(`.pregunta[data-pregunta="${preguntaNum}"]`);
  if (!bloque) throw new Error("No se encontró el bloque de la pregunta");

  const { cid, folio } = PRE.getParams();
  if (!cid && !folio) throw new Error("Falta cid o folio en la URL");

  const fd = new FormData();
  if (cid) fd.append("cid", cid);
  if (folio) fd.append("folio", folio);
  fd.append("seccion", `pagina_${PRE.state.seccionActual}`);
  fd.append("enviar_correo", "0");

  const r = bloque.querySelector(".respuesta")?.value ?? "";
  const c = bloque.querySelector(".comentarios")?.value ?? "";

  fd.append("pregunta[]", String(preguntaNum));
  fd.append("respuesta[]", r);
  fd.append("comentarios[]", c);

  const input = bloque.querySelector(".evidencia");
  if (input?.files?.length) {
    for (const file of input.files) {
      fd.append(`evidencia_${preguntaNum}[]`, file);
    }
  }

  const resp = await fetch("../php/guardar_respuestas.php", { method: "POST", body: fd });

  const raw = await resp.text();
  let data;
  try {
    data = JSON.parse(raw);
  } catch {
    console.error("No JSON:", raw);
    throw new Error("El servidor no devolvió JSON");
  }

  if (!resp.ok || !data.ok) {
    console.error(data);
    throw new Error(data.msg || "Error al guardar.");
  }

  await PRE.cargarRespuestasSeccion?.();
  await PRE.marcarPendientesSecciones?.();
  await PRE.marcarPendientesPreguntas?.();
  await PRE.actualizarTotalPendientes?.();
};
/* ============================================================
   Precargar respuestas/evidencias + pintar validación/bloqueo
   ============================================================ */
PRE.cargarRespuestasSeccion = async function cargarRespuestasSeccion() {
  const seccion = `pagina_${PRE.state.seccionActual}`;
  const { cid, folio } = PRE.getParams();

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

  const mapa = new Map();
  for (const r of data.respuestas) mapa.set(String(r.pregunta), r);

  const bloques = document.querySelectorAll(".pregunta[data-pregunta]");

  bloques.forEach((b) => {
    const num = b.getAttribute("data-pregunta");
    const guardado = mapa.get(String(num));

    const sel = b.querySelector(".respuesta");
    const txt = b.querySelector(".comentarios");

    if (guardado) {
      if (sel) sel.value = guardado.respuesta ?? "";
      if (txt) txt.value = guardado.comentarios ?? "";
    } else {
      if (sel) sel.value = "";
      if (txt) txt.value = "";
    }

    // Evidencias
    let evidencias = [];
    if (guardado && guardado.nom_evidencia) {
      try {
        evidencias = JSON.parse(guardado.nom_evidencia) || [];
      } catch {}
    }
    PRE.pintarEvidencias(b, evidencias);

    // Pintar verde si ya está validada
    PRE.aplicarUIValidada(b, guardado);

    // Bloquear totalmente si ya está validada
    PRE.aplicarBloqueoPorValidacion(b, guardado);
  });
};

/* ============================================================
   UI: pregunta validada
   ============================================================ */
PRE.aplicarUIValidada = function aplicarUIValidada(bloque, guardado) {
  const isValidada = guardado ? Number(guardado.validada || 0) === 1 : false;

  bloque.classList.remove("pregunta-validada");
  bloque.querySelector(".badge-validada")?.remove();

  if (isValidada) {
    bloque.classList.add("pregunta-validada");
    bloque.style.position = "relative";

    const badge = document.createElement("span");
    badge.className = "badge-validada";
    badge.textContent = "Validada ✓";
    bloque.appendChild(badge);
  }
};

/* ============================================================
   Bloquear edición si ya fue validada por el revisor
   ============================================================ */
PRE.aplicarBloqueoPorValidacion = function aplicarBloqueoPorValidacion(bloque, guardado) {
  const isValidada = guardado ? Number(guardado.validada || 0) === 1 : false;

  const sel = bloque.querySelector(".respuesta");
  const txt = bloque.querySelector(".comentarios");
  const inputEvidencia = bloque.querySelector(".evidencia");
  const contEvidencias = bloque.querySelector(".evidencias-guardadas");
  const btnNota = bloque.querySelector(".btn-nota");

  if (isValidada) {
    bloque.classList.add("pregunta-bloqueada");

    if (sel) {
      sel.disabled = true;
      sel.setAttribute("disabled", "disabled");
    }

    if (txt) {
      txt.disabled = true;
      txt.setAttribute("disabled", "disabled");
      txt.readOnly = true;
    }

    if (inputEvidencia) {
      inputEvidencia.disabled = true;
      inputEvidencia.setAttribute("disabled", "disabled");
    }

    if (btnNota) {
      btnNota.disabled = true;
      btnNota.classList.add("disabled");
      btnNota.setAttribute("title", "Pregunta validada por el revisor");
    }

    // Ocultar botones para eliminar evidencia
    if (contEvidencias) {
      contEvidencias.querySelectorAll("button").forEach((btn) => {
        btn.disabled = true;
        btn.style.display = "none";
      });
    }

  } else {
    bloque.classList.remove("pregunta-bloqueada");

    if (sel) {
      sel.disabled = false;
      sel.removeAttribute("disabled");
    }

    if (txt) {
      txt.disabled = false;
      txt.removeAttribute("disabled");
      txt.readOnly = false;
    }

    if (inputEvidencia) {
      inputEvidencia.disabled = false;
      inputEvidencia.removeAttribute("disabled");
    }

    if (btnNota) {
      btnNota.disabled = false;
      btnNota.classList.remove("disabled");
      btnNota.removeAttribute("title");
    }

    if (contEvidencias) {
      contEvidencias.querySelectorAll("button").forEach((btn) => {
        btn.disabled = false;
        btn.style.display = "";
      });
    }
  }
};

/* ============================================================
   Pintar evidencias (con botón eliminar)
   ============================================================ */
PRE.pintarEvidencias = function pintarEvidencias(bloquePregunta, evidencias) {
  const cont = bloquePregunta.querySelector(".evidencias-guardadas");
  if (!cont) return;

  const isValidada = bloquePregunta.classList.contains("pregunta-validada");
  const preguntaNum = bloquePregunta.getAttribute("data-pregunta");

  if (!Array.isArray(evidencias) || evidencias.length === 0) {
    cont.innerHTML = "<span class='text-muted'>Sin evidencias guardadas.</span>";
    return;
  }

  const items = evidencias
    .map((ev) => {
      const name = escapeHtml(ev.name || "archivo");
      const url = escapeHtml(ev.url || "#");

      const btnEliminar = isValidada
        ? ""
        : `
          <button type="button" class="btn btn-sm btn-outline-danger ms-auto"
                  onclick="eliminarEvidencia('${PRE.state.seccionActual}','${preguntaNum}','${url.replace(/'/g, "\\'")}')">
            🗑️
          </button>
        `;

      return `
        <li class="d-flex align-items-center gap-2">
          <a href="../${url}" target="_blank" rel="noopener">${name}</a>
          ${btnEliminar}
        </li>
      `;
    })
    .join("");

  cont.innerHTML = `<div><strong>Evidencias guardadas:</strong></div><ul class="mb-0">${items}</ul>`;
};

/* ============================================================
   Eliminar evidencia
   ============================================================ */
PRE.eliminarEvidencia = async function eliminarEvidencia(seccionNum, preguntaNum, url) {
  const bloque = document.querySelector(`.pregunta[data-pregunta="${preguntaNum}"]`);
  if (bloque?.classList.contains("pregunta-validada")) {
    alert("Esta pregunta ya fue validada por el revisor y no puede modificarse.");
    return;
  }

  if (!confirm("¿Deseas eliminar este documento?")) return;

  const { cid, folio } = PRE.getParams();

  try {
    const fd = new FormData();
    if (cid) fd.append("cid", cid);
    if (folio) fd.append("folio", folio);
    fd.append("seccion", `pagina_${seccionNum}`);
    fd.append("pregunta", String(preguntaNum));
    fd.append("url", url);

    const resp = await fetch("../php/eliminar_evidencia.php", { method: "POST", body: fd });
    const data = await resp.json();

    if (!resp.ok || !data.ok) {
      console.error(data);
      alert(data.msg || "No se pudo eliminar.");
      return;
    }

    alert(data.msg || "Documento eliminado ✅");
    await PRE.cargarRespuestasSeccion();
  } catch (e) {
    console.error(e);
    alert("Error de red/servidor al eliminar evidencia.");
  }
};

window.eliminarEvidencia = PRE.eliminarEvidencia;