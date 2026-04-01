<?php
header("Content-Type: application/json; charset=utf-8");
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION["nombre"])) {
  http_response_code(401);
  echo json_encode(["ok" => false, "msg" => "No autorizado"]);
  exit;
}

require_once __DIR__ . "/conexion.php";

try {
  $mysqli = db();

  $tipoUsuario = $_SESSION["tipo_usuario"] ?? "";
  $usuarioId   = (int)($_SESSION["id"] ?? 0);

  // =========================================================
  // Si el usuario es revisor, solo verá sus cuestionarios eliminados
  // Si no lo es, verá todos los eliminados
  // =========================================================
  if ($tipoUsuario === "revisor") {
    $sql = "
      SELECT
        id,
        folio,
        fecha_creacion,
        fecha_vencimiento,
        estatus,
        creado_por,
        actualizado_por,
        actualizado_en,
        eliminado_por,
        eliminado_en
      FROM cuestionarios
      WHERE eliminado = 1
        AND revisor_id = ?
      ORDER BY eliminado_en DESC
      LIMIT 200
    ";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
      throw new Exception("Error prepare: " . $mysqli->error);
    }

    $stmt->bind_param("i", $usuarioId);
    $stmt->execute();
    $res = $stmt->get_result();
  } else {
    $sql = "
      SELECT
        id,
        folio,
        fecha_creacion,
        fecha_vencimiento,
        estatus,
        creado_por,
        actualizado_por,
        actualizado_en,
        eliminado_por,
        eliminado_en
      FROM cuestionarios
      WHERE eliminado = 1
      ORDER BY eliminado_en DESC
      LIMIT 200
    ";

    $res = $mysqli->query($sql);

    if (!$res) {
      throw new Exception("Error en la consulta SQL: " . $mysqli->error);
    }
  }

  $rows = [];
  while ($r = $res->fetch_assoc()) {
    $rows[] = $r;
  }

  echo json_encode([
    "ok" => true,
    "cuestionarios" => $rows
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    "ok" => false,
    "msg" => "Error en servidor",
    "detalle" => $e->getMessage()
  ], JSON_UNESCAPED_UNICODE);
}