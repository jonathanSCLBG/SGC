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
  $seccion = trim($_GET["seccion"] ?? "");
  $pregunta = trim($_GET["pregunta"] ?? ""); // opcional

  if ($cid <= 0 || $seccion === "") throw new Exception("Faltan parámetros (cid/seccion).");

  if ($pregunta !== "") {
    $stmt = $mysqli->prepare("
      SELECT id, pregunta, nota, revisor_nombre, creado_en, resuelta, resuelta_en, resuelta_por
      FROM notas_revision
      WHERE cuestionario_id = ? AND seccion = ? AND pregunta = ?
      ORDER BY creado_en DESC
    ");
    $stmt->bind_param("iss", $cid, $seccion, $pregunta);
  } else {
    $stmt = $mysqli->prepare("
      SELECT id, pregunta, nota, revisor_nombre, creado_en, resuelta, resuelta_en, resuelta_por
      FROM notas_revision
      WHERE cuestionario_id = ? AND seccion = ?
      ORDER BY creado_en DESC
    ");
    $stmt->bind_param("is", $cid, $seccion);
  }

  $stmt->execute();
  $res = $stmt->get_result();

  $rows = [];
  while ($r = $res->fetch_assoc()) $rows[] = $r;
  $stmt->close();

  echo json_encode(["ok"=>true,"notas"=>$rows], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(["ok"=>false,"msg"=>"Error en servidor","detalle"=>$e->getMessage()]);
}
