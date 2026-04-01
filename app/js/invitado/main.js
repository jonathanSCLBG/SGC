import { cargarCuestionario } from "./cuestionario.js";

window.INV = {
  cargarCuestionario
};

window.addEventListener("DOMContentLoaded", async () => {
  await INV.cargarCuestionario(1);
});