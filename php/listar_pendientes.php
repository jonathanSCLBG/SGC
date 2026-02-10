<?php
header("Content-Type: application/json; charset=utf-8");
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/conexion.php";
session_start();

if (!isset($_SESSION["nombre"])) {
  http_response_code(401);
  echo json_encode(["ok"=>false,"msg"=>"No autorizado"]);
  exit;
}

try {
  $mysqli = db();

  $cid = intval($_GET["cid"] ?? 0);
  if ($cid <= 0) throw new Exception("Falta cid.");

  // Trae SOLO pendientes, agrupado por seccion/pregunta, con:
  // - cuántas pendientes hay
  // - fecha de la última nota pendiente (para ordenar)
  $stmt = $mysqli->prepare("
    SELECT
      seccion,
      pregunta,
      COUNT(*) AS pendientes,
      MAX(creado_en) AS ultima_nota
    FROM notas_revision
    WHERE cuestionario_id = ? AND resuelta = 0
    GROUP BY seccion, pregunta
    ORDER BY ultima_nota DESC
  ");
  $stmt->bind_param("i", $cid);
  $stmt->execute();
  $res = $stmt->get_result();

  $rows = [];
  while ($r = $res->fetch_assoc()) $rows[] = $r;
  $stmt->close();

  echo json_encode(["ok"=>true,"pendientes"=>$rows], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(["ok"=>false,"msg"=>"Error en servidor","detalle"=>$e->getMessage()]);
}
