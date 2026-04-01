// js/invitado/cuestionario.js
import { STATE } from "./state.js";

export function marcarActivo(numero) {
  qsa(".nav-link").forEach(a => a.classList.remove("active"));
  qsa(".nav-link")[numero - 1]?.classList.add("active");
}

export async function cargarCuestionario(numero) {
  STATE.seccionActual = numero;

  try {
    const r = await fetch(`pagina_${numero}.html`, { cache: "no-store" });

    if (!r.ok) {
      throw new Error(`No se pudo cargar pagina_${numero}.html (status ${r.status})`);
    }

    const html = await r.text();

    qs("#contenedor-cuestionario").innerHTML = html;
    marcarActivo(numero);

    await cargarRespuestasSeccion();
    bloquearInputs();
    ocultarElementos();
    convertirInputsAParrafos();
  } catch (err) {
    console.error(err);
    qs("#contenedor-cuestionario").innerHTML = `
      <div class="alert alert-danger">
        Error al cargar el cuestionario.
      </div>
    `;
  }
}

export async function cargarRespuestasSeccion() {
  const seccion = `pagina_${STATE.seccionActual}`;

  const url = new URL("../php/obtener_respuestas.php", window.location.href);
  if (STATE.cid) url.searchParams.set("cid", STATE.cid);
  if (STATE.folio) url.searchParams.set("folio", STATE.folio);
  url.searchParams.set("seccion", seccion);

  const resp = await fetch(url.toString(), { cache: "no-store" });
  const data = await resp.json();

  if (!resp.ok || !data.ok) {
    console.error("Error al obtener respuestas:", data);
    return;
  }

  const mapa = new Map();
  for (const r of (data.respuestas || [])) {
    mapa.set(String(r.pregunta), r);
  }

  const form = qs("#form-cuestionario");
  const bloques = qsa(".pregunta[data-pregunta]", form);

  bloques.forEach((b) => {
    const num = b.getAttribute("data-pregunta");
    const guardado = mapa.get(num);

    const sel = b.querySelector(".respuesta");
    const txt = b.querySelector(".comentarios");

    if (guardado) {
      if (sel) sel.value = guardado.respuesta ?? "";
      if (txt) txt.value = guardado.comentarios ?? "";
    } else {
      if (sel) sel.value = "";
      if (txt) txt.value = "";
    }

    let evidencias = [];
    if (guardado && guardado.nom_evidencia) {
      try {
        evidencias = JSON.parse(guardado.nom_evidencia) || [];
      } catch {
        evidencias = [];
      }
    }

    pintarEvidencias(b, evidencias);
  });
}

export function pintarEvidencias(bloquePregunta, evidencias) {
  const cont = bloquePregunta.querySelector(".evidencias-guardadas");
  if (!cont) return;

  if (!Array.isArray(evidencias) || evidencias.length === 0) {
    cont.innerHTML = "<span class='text-muted'>Sin evidencias guardadas.</span>";
    return;
  }

  const items = evidencias.map(ev => {
    const name = escapeHtml(ev.name || "archivo");
    const url = escapeHtml(ev.url || "#");
    return `<li><a href="../${url}" target="_blank" rel="noopener">${name}</a></li>`;
  }).join("");

  cont.innerHTML = `<div><strong>Evidencias guardadas:</strong></div><ul class="mb-0">${items}</ul>`;
}

export function bloquearInputs() {
  const form = qs("#form-cuestionario");
  qsa("select.respuesta, textarea.comentarios, input.evidencia", form)
    .forEach(el => el.disabled = true);

  const btnGuardar = qs("#contenedor-cuestionario #btnGuardar");
  if (btnGuardar) btnGuardar.style.display = "none";
}

export function ocultarElementos() {
  qsa("#contenedor-cuestionario .contDrops").forEach(el => el.style.display = "none");
  qsa("#contenedor-cuestionario .inputEvidencia").forEach(el => el.style.display = "none");
  qsa("#contenedor-cuestionario .labelComentarios").forEach(el => el.style.display = "none");
}

export function convertirInputsAParrafos() {
  const form = qs("#form-cuestionario");
  const bloques = qsa(".pregunta[data-pregunta]", form);

  bloques.forEach((b) => {
    const sel = b.querySelector("select.respuesta");
    const txt = b.querySelector("textarea.comentarios");

    if (sel) {
      const optionText = sel.selectedOptions?.[0]?.textContent?.trim() || "";
      const pSel = document.createElement("p");
      pSel.className = "respuesta-p form-control-plaintext mb-2";
      pSel.textContent = optionText || "—";
      sel.style.display = "none";
      sel.insertAdjacentElement("afterend", pSel);
    }

    if (txt) {
      const pTxt = document.createElement("p");
      pTxt.className = "comentarios-p form-control-plaintext";
      pTxt.style.whiteSpace = "pre-wrap";
      pTxt.textContent = (txt.value || "").trim() || "—";
      txt.style.display = "none";
      txt.insertAdjacentElement("afterend", pTxt);
    }
  });
}