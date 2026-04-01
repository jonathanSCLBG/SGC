// js/invitado/state.js
export const STATE = {
  seccionActual: 1,
  cid: new URLSearchParams(window.location.search).get("cid") || "",
  folio: new URLSearchParams(window.location.search).get("folio") || ""
};