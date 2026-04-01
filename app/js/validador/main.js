window.REV = window.REV || {};

document.addEventListener("DOMContentLoaded", async () => {
  // Tooltips
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => new bootstrap.Tooltip(el));

  // ✅ 1) Cargar usuario sesión (esto quita el "Cargando...")
  if (typeof REV.cargarUsuarioSesion === "function") {
    await REV.cargarUsuarioSesion();
  } else {
    console.warn("REV.cargarUsuarioSesion no está disponible. Revisa el orden de scripts.");
  }

  // ✅ 2) Cargar sección inicial
  if (typeof REV.cargarCuestionario === "function") {
    REV.cargarCuestionario(1);
  }

  // ✅ 3) Listener Validar / Desvalidar
  const btnValidar = document.getElementById("btnValidarRespuesta");
  if (btnValidar) {
    btnValidar.addEventListener("click", async () => {
      const ctx = REV.state.notaContext;

      if (!ctx?.cid || !ctx?.seccion || !ctx?.pregunta) {
        alert("No hay contexto de pregunta. Abre una pregunta y vuelve a intentar.");
        return;
      }

      const p = String(ctx.pregunta);
      const isValidada = Number(REV.state.validadaPorPregunta?.[p] || 0) === 1;

      // ❌ No permitir validar si no hay respuesta
      if (!isValidada) {
        const respGuardada = (REV.state.respuestaPorPregunta?.[p] ?? "").trim();
        if (!respGuardada) {
          alert("No puedes validar esta pregunta porque no tiene respuesta.");
          return;
        }
      }

      // Si va a VALIDAR (0 -> 1), exigimos que no haya pendientes
      if (!isValidada) {
        const btnPregunta = document.querySelector(`.pregunta[data-pregunta="${p}"] .btn-nota`);
        const tienePendientes = btnPregunta && btnPregunta.hasAttribute("data-badge");
        if (tienePendientes) {
          alert("Esta pregunta tiene notas pendientes. Resuélvelas antes de validar.");
          return;
        }
      }

      try {
        const fd = new FormData();
        fd.append("cid", ctx.cid);
        fd.append("seccion", ctx.seccion);
        fd.append("pregunta", p);
        fd.append("validada", isValidada ? "0" : "1"); // toggle

        const resp = await fetch("../php/validar_respuesta.php", { method: "POST", body: fd });
        const data = await resp.json();

        if (!resp.ok || !data.ok) {
          console.error(data);
          alert(data.msg || "No se pudo actualizar la validación.");
          return;
        }

        // Refrescamos estado y UI
        await REV.cargarRespuestasSeccion();
        await REV.marcarPendientesPreguntas?.();
        await REV.marcarPendientesSecciones?.();
        await REV.actualizarTotalPendientes?.();

        // 🔁 Refresca historial para bloquear/desbloquear botones
        await REV.cargarHistorialNotas?.();

        REV.aplicarBloqueoPorValidacion?.();
      } catch (e) {
        console.error(e);
        alert("Error de servidor al actualizar la validación.");
      }
    });
  }
});
