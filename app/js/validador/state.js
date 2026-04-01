window.REV = window.REV || {};

REV.state = {
  seccionActual: 1,
  notaContext: null,

  // Mapa pregunta => 0/1 validada
  validadaPorPregunta: {},

  // ✅ NUEVO: Mapa pregunta => respuesta guardada
  respuestaPorPregunta: {}
};

REV.getParams = function () {
  const sp = new URLSearchParams(window.location.search);
  return {
    cid: sp.get("cid"),
    folio: sp.get("folio"),
  };
};
