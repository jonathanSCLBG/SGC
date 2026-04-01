<?php
header("Content-Type: application/json; charset=utf-8");
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . "/conexion.php";

try {
  if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode([
      "ok" => false,
      "msg" => "Método no permitido"
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $input = json_decode(file_get_contents("php://input"), true);

  $id = isset($input["id"]) ? (int)$input["id"] : 0;
  $folio = isset($input["folio"]) ? trim($input["folio"]) : "";

  if ($id <= 0) {
    http_response_code(400);
    echo json_encode([
      "ok" => false,
      "msg" => "ID inválido"
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $mysqli = db();

  $sql = "
    UPDATE cuestionarios
    SET
      eliminado = 0,
      eliminado_por = NULL,
      eliminado_en = NULL
    WHERE id = ?
      AND eliminado = 1
  ";

  $stmt = $mysqli->prepare($sql);
  if (!$stmt) {
    throw new Exception("Error al preparar la consulta: " . $mysqli->error);
  }

  $stmt->bind_param("i", $id);
  $stmt->execute();

  if ($stmt->affected_rows <= 0) {
    echo json_encode([
      "ok" => false,
      "msg" => "El cuestionario no existe o no está en papelera"
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $stmt->close();

  echo json_encode([
    "ok" => true,
    "msg" => "El cuestionario {$folio} fue restaurado correctamente"
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    "ok" => false,
    "msg" => "Error en servidor",
    "detalle" => $e->getMessage()
  ], JSON_UNESCAPED_UNICODE);
}