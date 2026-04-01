<?php
session_start();

if (!isset($_SESSION['id'])) {
  header("Location: login.html");
  exit;
}

if (!isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] !== 'validador') {
  header("Location: login.html");
  exit;
}

$nombreUsuario = $_SESSION['nombre'] ?? 'Usuario';
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>SGC - Validación de cuestionarios</title>

    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
      rel="stylesheet"
    />

    <style>
      body { background: #f6f7fb; }

      .topbar { background: #28334f; color: #fff; }

      .cont-drop { display: inline-block; position: relative; }
      .usuario, .accion {
        display: block;
        padding: 6px 25px;
        color: #a3acc0;
        text-decoration: none;
        text-align: center;
        margin: 0;
        border-radius: 10px 10px 0 0;
        cursor: default;
        user-select: none;
        white-space: nowrap;
      }
      .accion { padding: 6px 5px; border-radius: 10px; cursor: pointer; }
      .dropdown {
        width: 100%;
        background: #323c55;
        position: absolute;
        z-index: 999;
        display: none;
        border-radius: 0 0 10px 10px;
      }
      .accion:hover, .usuario:hover { background: #323c55; color: #fff; }
      .cont-drop:hover .dropdown { display: block; }

      .card-shadow {
        box-shadow: 0 8px 24px rgba(0,0,0,.08);
        border: 0;
        border-radius: 14px;
      }

      .mono {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
      }

      .footer {
        margin: auto;
        width: 100%;
        max-width: 1100px;
        text-align: center;
        color: #1f2937;
        padding: 18px 8px;
      }

      .nav-pills .nav-link {
        color: #28334f;
        font-weight: 600;
        border-radius: 10px;
      }

      .nav-pills .nav-link.active {
        background-color: #28334f;
        color: #fff;
      }

      .tabs-wrap {
        padding: 14px 14px 0 14px;
      }

      .table-wrap {
        padding: 10px 10px 14px 10px;
      }

      .table td, .table th {
        vertical-align: middle;
      }

      .tab-pane {
        min-height: 470px;
      }

      .btn-azul {
        background-color: #28334f;
        color: #fff;
        border: 1px solid #28334f;
      }

      .btn-azul:hover {
        background-color: #1f2940;
        color: #fff;
        border-color: #1f2940;
      }
    </style>
  </head>

  <body>
    <!-- TOP BAR -->
    <div class="topbar py-3">
      <div
        class="container d-flex align-items-center justify-content-between"
        style="display:flex; justify-content:center; align-items:center; width:100%; max-width:1100px;"
      >
        <div class="d-flex align-items-center gap-3">
          <div class="bg-white rounded px-2 py-1">
            <img
              src="https://sclconsultores.com.mx/imagenesRegistros/logo.svg"
              alt="logo"
              height="34"
            />
          </div>
          <div>
            <div class="fw-semibold">Sistema de Gestión de Calidad</div>
            <div class="small opacity-75">Validación de cuestionarios</div>
          </div>
        </div>

        <div class="cont-drop">
          <p class="usuario" id="lblUsuario"><?= htmlspecialchars($nombreUsuario) ?> ▼</p>
          <div class="dropdown">
            <a href="php/logout.php" class="accion">Cerrar Sesión</a>
          </div>
        </div>
      </div>
    </div>

    <!-- CONTENIDO -->
    <div
      class="container my-4"
      style="
        display:flex;
        justify-content:center;
        width:100%;
        max-width:1100px;
        min-height:70vh;
        border-radius:14px;
        box-shadow:10px 18px 45px #111827;
        overflow:hidden;
        padding:0;
      "
    >
      <div class="card card-shadow" style="width:100%">
        <div class="card-body" style="padding:0">
          <div
            style="
              display:flex;
              justify-content:space-between;
              align-items:center;
              background-color:#28334f;
            "
          >
            <h5
              class="card-title mb-3"
              style="margin:0; padding:20px; color:#f6f7fb"
            >
              Panel de validación
            </h5>
          </div>

          <div class="tabs-wrap">
            <ul class="nav nav-pills gap-2" id="tabsValidador" role="tablist">
              <li class="nav-item" role="presentation">
                <button
                  class="nav-link active"
                  id="tab-por-validar"
                  data-bs-toggle="pill"
                  data-bs-target="#pane-por-validar"
                  type="button"
                  role="tab"
                >
                  
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-clock-history" viewBox="0 0 16 16">
                  <path d="M8.515 1.019A7 7 0 0 0 8 1V0a8 8 0 0 1 .589.022zm2.004.45a7 7 0 0 0-.985-.299l.219-.976q.576.129 1.126.342zm1.37.71a7 7 0 0 0-.439-.27l.493-.87a8 8 0 0 1 .979.654l-.615.789a7 7 0 0 0-.418-.302zm1.834 1.79a7 7 0 0 0-.653-.796l.724-.69q.406.429.747.91zm.744 1.352a7 7 0 0 0-.214-.468l.893-.45a8 8 0 0 1 .45 1.088l-.95.313a7 7 0 0 0-.179-.483m.53 2.507a7 7 0 0 0-.1-1.025l.985-.17q.1.58.116 1.17zm-.131 1.538q.05-.254.081-.51l.993.123a8 8 0 0 1-.23 1.155l-.964-.267q.069-.247.12-.501m-.952 2.379q.276-.436.486-.908l.914.405q-.24.54-.555 1.038zm-.964 1.205q.183-.183.35-.378l.758.653a8 8 0 0 1-.401.432z"/>
                  <path d="M8 1a7 7 0 1 0 4.95 11.95l.707.707A8.001 8.001 0 1 1 8 0z"/>
                  <path d="M7.5 3a.5.5 0 0 1 .5.5v5.21l3.248 1.856a.5.5 0 0 1-.496.868l-3.5-2A.5.5 0 0 1 7 9V3.5a.5.5 0 0 1 .5-.5"/>
                </svg> Por validar
                </button>
              </li>

              <li class="nav-item" role="presentation">
                <button
                  class="nav-link"
                  id="tab-incompletos"
                  data-bs-toggle="pill"
                  data-bs-target="#pane-incompletos"
                  type="button"
                  role="tab"
                >
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-exclamation-triangle-fill" viewBox="0 0 16 16">
                  <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5m.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2"/>
                </svg> Incompletos
                </button>
              </li>

              <li class="nav-item" role="presentation">
                <button
                  class="nav-link"
                  id="tab-mis-validados"
                  data-bs-toggle="pill"
                  data-bs-target="#pane-mis-validados"
                  type="button"
                  role="tab"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-check2-circle" viewBox="0 0 16 16">
                    <path d="M2.5 8a5.5 5.5 0 0 1 8.25-4.764.5.5 0 0 0 .5-.866A6.5 6.5 0 1 0 14.5 8a.5.5 0 0 0-1 0 5.5 5.5 0 1 1-11 0"/>
                    <path d="M15.354 3.354a.5.5 0 0 0-.708-.708L8 9.293 5.354 6.646a.5.5 0 1 0-.708.708l3 3a.5.5 0 0 0 .708 0z"/>
                  </svg> Mis validados
                </button>
              </li>
            </ul>
          </div>

          <div class="tab-content">
            <div class="tab-pane fade show active" id="pane-por-validar" role="tabpanel">
              <div class="table-wrap">
                <div class="table-responsive">
                  <table class="table table-striped table-hover align-middle mb-0">
                    <thead class="table-dark">
                      <tr>
                        <th>ID</th>
                        <th>Folio</th>
                        <th>Creación</th>
                        <th>Vencimiento</th>
                        <th>Estatus</th>
                        <th>Creador</th>
                        <th>Revisor</th>
                        <th>Última modificación</th>
                        <th style="width: 210px">Acción</th>
                      </tr>
                    </thead>
                    <tbody id="tbodyPorValidar">
                      <tr><td colspan="9" class="text-center text-muted">Cargando...</td></tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <div class="tab-pane fade" id="pane-incompletos" role="tabpanel">
              <div class="table-wrap">
                <div class="table-responsive">
                  <table class="table table-striped table-hover align-middle mb-0">
                    <thead class="table-dark">
                      <tr>
                        <th>ID</th>
                        <th>Folio</th>
                        <th>Creación</th>
                        <th>Vencimiento</th>
                        <th>Estatus</th>
                        <th>Creador</th>
                        <th>Revisor</th>
                        <th style="width: 160px">Acción</th>
                      </tr>
                    </thead>
                    <tbody id="tbodyIncompletos">
                      <tr><td colspan="8" class="text-center text-muted">Cargando...</td></tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <div class="tab-pane fade" id="pane-mis-validados" role="tabpanel">
              <div class="table-wrap">
                <div class="table-responsive">
                  <table class="table table-striped table-hover align-middle mb-0">
                    <thead class="table-dark">
                      <tr>
                        <th>ID</th>
                        <th>Folio</th>
                        <th>Creación</th>
                        <th>Vencimiento</th>
                        <th>Estatus</th>
                        <th>Creador</th>
                        <th>Revisor</th>
                        <th>Validado por</th>
                        <th style="width: 160px">Acción</th>
                      </tr>
                    </thead>
                    <tbody id="tbodyMisValidados">
                      <tr><td colspan="9" class="text-center text-muted">Cargando...</td></tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>

    <footer class="footer" role="contentinfo">
      <small>
        © 2026 SCL Consultores ·
        <a href="" style="text-decoration: none; color: inherit">#Política de privacidad</a>
        ·
        <a href="" style="text-decoration: none; color: inherit">Términos</a>
      </small>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

    <script>
      function escapeHtml(str) {
        return String(str ?? "")
          .replaceAll("&", "&amp;")
          .replaceAll("<", "&lt;")
          .replaceAll(">", "&gt;")
          .replaceAll('"', "&quot;")
          .replaceAll("'", "&#039;");
      }

      function normalizar(x) {
        return String(x ?? "").trim().toLowerCase();
      }

      const RESPUESTA_PLACEHOLDER = new Set([
        "",
        "selecciona",
        "selecciona...",
        "selecciona tu respuesta",
        "selecciona tu respuesta...",
        "seleccione",
        "seleccione...",
        "seleccione una opción",
        "seleccione una opción..."
      ]);

      function esRespuestaValida(valorRespuesta) {
        const v = normalizar(valorRespuesta);
        if (!v) return false;
        if (RESPUESTA_PLACEHOLDER.has(v)) return false;
        return true;
      }

      const MAX_CUESTIONARIOS_A_REVISAR = 50;

      function obtenerUsuarioActual() {
        const txt = document.getElementById("lblUsuario")?.textContent || "";
        return txt.replace("▼", "").trim();
      }

      function obtenerNombreRevisor(q) {
        return q.revisor || q.revisor_nombre || q.nombre_revisor || "-";
      }

      async function obtenerMetricasCuestionario(cid, folio) {
        let total = 0;
        let respondidas = 0;
        let validadas = 0;

        for (let i = 1; i <= 8; i++) {
          const url = new URL("php/obtener_respuestas.php", window.location.href);
          url.searchParams.set("cid", String(cid));
          if (folio) url.searchParams.set("folio", String(folio));
          url.searchParams.set("seccion", `pagina_${i}`);

          const resp = await fetch(url.toString(), { cache: "no-store" });
          const data = await resp.json();

          if (!resp.ok || !data.ok) {
            console.error("Error obtener_respuestas:", cid, `pagina_${i}`, data);
            return null;
          }

          const arr = Array.isArray(data.respuestas) ? data.respuestas : [];
          for (const r of arr) {
            total++;
            const okRespuesta = esRespuestaValida(r.respuesta);
            if (okRespuesta) {
              respondidas++;
              if (Number(r.validada || 0) === 1) {
                validadas++;
              }
            }
          }
        }

        return { total, respondidas, validadas };
      }

      function renderFilaPorValidar(q) {
        const linkVer = `app/sgc_validador.html?cid=${q.id}&folio=${encodeURIComponent(q.folio)}`;

        return `
          <tr>
            <td>${q.id}</td>
            <td class="mono"><strong>${escapeHtml(q.folio)}</strong></td>
            <td>${escapeHtml(q.fecha_creacion || "-")}</td>
            <td>${escapeHtml(q.fecha_vencimiento || "-")}</td>
            <td><span class="badge bg-primary">Por validar</span></td>
            <td>${escapeHtml(q.creado_por || "-")}</td>
            <td>${escapeHtml(obtenerNombreRevisor(q))}</td>
            <td>
              <div class="small">
                <div><strong>${escapeHtml(q.actualizado_por || "-")}</strong></div>
                <div class="text-muted">${escapeHtml(q.actualizado_en || "-")}</div>
              </div>
            </td>
            <td>
              <a href="${linkVer}" class="btn btn-dark btn-sm">Ver</a>
              <button class="btn btn-success btn-sm" onclick="validarCuestionario(${q.id})">Validar</button>
            </td>
          </tr>
        `;
      }

      function renderFilaIncompleto(q, met) {
        const link = `app/sgc_validador.html?cid=${q.id}&folio=${encodeURIComponent(q.folio)}`;

        return `
          <tr>
            <td>${q.id}</td>
            <td class="mono"><strong>${escapeHtml(q.folio)}</strong></td>
            <td>${escapeHtml(q.fecha_creacion || "-")}</td>
            <td>${escapeHtml(q.fecha_vencimiento || "-")}</td>
            <td><span class="badge bg-warning text-dark">Incompleto</span></td>
            <td>${escapeHtml(q.creado_por || "-")}</td>
            <td>${escapeHtml(obtenerNombreRevisor(q))}</td>
            <td>
              <a href="${link}" class="btn btn-dark btn-sm">Ver</a>
            </td>
          </tr>
        `;
      }

      function renderFilaMisValidados(q) {
        const link = `app/sgc_validador.html?cid=${q.id}&folio=${encodeURIComponent(q.folio)}`;

        return `
          <tr>
            <td>${q.id}</td>
            <td class="mono"><strong>${escapeHtml(q.folio)}</strong></td>
            <td>${escapeHtml(q.fecha_creacion || "-")}</td>
            <td>${escapeHtml(q.fecha_vencimiento || "-")}</td>
            <td><span class="badge bg-success">Validado</span></td>
            <td>${escapeHtml(q.creado_por || "-")}</td>
            <td>${escapeHtml(obtenerNombreRevisor(q))}</td>
            <td>
              <div class="small">
                <div><strong>${escapeHtml(q.validado_por || "-")}</strong></div>
                <div class="text-muted">${escapeHtml(q.validado_en || "-")}</div>
              </div>
            </td>
            <td>
              <a href="${link}" class="btn btn-dark btn-sm">Ver</a>
            </td>
          </tr>
        `;
      }

      async function validarCuestionario(id) {
        try {
          const usuario = obtenerUsuarioActual();

          if (!confirm("¿Deseas validar este cuestionario?")) {
            return;
          }

          const formData = new FormData();
          formData.append("id", id);
          formData.append("usuario", usuario);

          const resp = await fetch("php/validar_cuestionario.php", {
            method: "POST",
            body: formData
          });

          const data = await resp.json();

          if (!resp.ok || !data.ok) {
            alert(data.msg || "Error al validar");
            return;
          }

          alert("Cuestionario validado correctamente");
          await cargarTablasValidador();

        } catch (error) {
          console.error(error);
          alert("Error de servidor al validar");
        }
      }

      async function cargarTablasValidador() {
        const tbodyPorValidar = document.getElementById("tbodyPorValidar");
        const tbodyIncompletos = document.getElementById("tbodyIncompletos");
        const tbodyMisValidados = document.getElementById("tbodyMisValidados");

        tbodyPorValidar.innerHTML = `<tr><td colspan="9" class="text-center text-muted">Cargando...</td></tr>`;
        tbodyIncompletos.innerHTML = `<tr><td colspan="8" class="text-center text-muted">Cargando...</td></tr>`;
        tbodyMisValidados.innerHTML = `<tr><td colspan="9" class="text-center text-muted">Cargando...</td></tr>`;

        try {
          const resp = await fetch("php/listar_cuestionarios.php", { cache: "no-store" });
          const data = await resp.json();

          console.log("listar_cuestionarios =>", data);

          if (!resp.ok || !data.ok) {
            tbodyPorValidar.innerHTML = `<tr><td colspan="9" class="text-center text-danger">Error al cargar</td></tr>`;
            tbodyIncompletos.innerHTML = `<tr><td colspan="8" class="text-center text-danger">Error al cargar</td></tr>`;
            tbodyMisValidados.innerHTML = `<tr><td colspan="9" class="text-center text-danger">Error al cargar</td></tr>`;
            return;
          }

          const lista = Array.isArray(data.cuestionarios) ? data.cuestionarios : [];

          if (!lista.length) {
            tbodyPorValidar.innerHTML = `<tr><td colspan="9" class="text-center text-muted">No hay cuestionarios</td></tr>`;
            tbodyIncompletos.innerHTML = `<tr><td colspan="8" class="text-center text-muted">No hay cuestionarios</td></tr>`;
            tbodyMisValidados.innerHTML = `<tr><td colspan="9" class="text-center text-muted">No hay cuestionarios</td></tr>`;
            return;
          }

          const usuarioActual = normalizar(obtenerUsuarioActual());
          const revisar = lista.slice(0, MAX_CUESTIONARIOS_A_REVISAR);

          const porValidar = [];
          const incompletos = [];
          const misValidados = [];

          for (const q of revisar) {
            const met = await obtenerMetricasCuestionario(q.id, q.folio);
            console.log("Cuestionario", q.id, q.folio, met);

            if (!met) continue;

            const completo = met.total > 0 && met.respondidas === met.total;
            const revisadoCompleto = met.total > 0 && met.validadas === met.total;

            const yaValidado = normalizar(q.estatus_validacion) === "validado";
            const validadoPorMi =
              yaValidado &&
              normalizar(q.validado_por) === usuarioActual;

            if (validadoPorMi) {
              misValidados.push(q);
              continue;
            }

            if (completo && revisadoCompleto && !yaValidado) {
              porValidar.push(q);
              continue;
            }

            if (!completo) {
              incompletos.push({ q, met });
            }
          }

          tbodyPorValidar.innerHTML = porValidar.length
            ? porValidar.map(renderFilaPorValidar).join("")
            : `<tr><td colspan="9" class="text-center text-muted">No hay cuestionarios por validar.</td></tr>`;

          tbodyIncompletos.innerHTML = incompletos.length
            ? incompletos.map(item => renderFilaIncompleto(item.q, item.met)).join("")
            : `<tr><td colspan="8" class="text-center text-muted">No hay cuestionarios incompletos.</td></tr>`;

          tbodyMisValidados.innerHTML = misValidados.length
            ? misValidados.map(renderFilaMisValidados).join("")
            : `<tr><td colspan="9" class="text-center text-muted">Aún no has validado cuestionarios.</td></tr>`;

        } catch (e) {
          console.error(e);
          tbodyPorValidar.innerHTML = `<tr><td colspan="9" class="text-center text-danger">Error de servidor</td></tr>`;
          tbodyIncompletos.innerHTML = `<tr><td colspan="8" class="text-center text-danger">Error de servidor</td></tr>`;
          tbodyMisValidados.innerHTML = `<tr><td colspan="9" class="text-center text-danger">Error de servidor</td></tr>`;
        }
      }

      document.addEventListener("DOMContentLoaded", async () => {
        await cargarTablasValidador();
      });
    </script>
  </body>
</html>