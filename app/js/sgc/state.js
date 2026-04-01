window.PRE = window.PRE || {};

PRE.state = {
  seccionActual: 1,
  modalNotas: null,
  modalPendientes: null,
  notaContext: { pregunta: null, seccion: null, cid: null, folio: null },
};

PRE.getParams = function getParams() {
  const sp = new URLSearchParams(window.location.search);
  return {
    cid: sp.get("cid"),
    folio: sp.get("folio"),
  };
};
