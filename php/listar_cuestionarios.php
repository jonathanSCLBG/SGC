<?php
header("Content-Type: application/json; charset=utf-8");
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/conexion.php";

try {
  $mysqli = db();

  $sql = "
    SELECT
      id,
      folio,
      fecha_creacion,
      fecha_vencimiento,
      estatus,
      creado_por,
      actualizado_por,
      actualizado_en
    FROM cuestionarios
    ORDER BY fecha_creacion DESC
    LIMIT 200
  ";

  $res = $mysqli->query($sql);

  $rows = [];
  while ($r = $res->fetch_assoc()) {
    $rows[] = $r;
  }

  echo json_encode(["ok" => true, "cuestionarios" => $rows], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    "ok" => false,
    "msg" => "Error en servidor",
    "detalle" => $e->getMessage()
  ], JSON_UNESCAPED_UNICODE);
}
