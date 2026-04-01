// js/shared/utils.js

window.qs = function (selector, ctx = document) {
  return ctx.querySelector(selector);
};

window.qsa = function (selector, ctx = document) {
  return Array.from(ctx.querySelectorAll(selector));
};

window.escapeHtml = function (str = "") {
  return String(str)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
};

window.sleep = function (ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
};