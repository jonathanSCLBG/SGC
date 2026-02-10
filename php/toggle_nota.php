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

  $id = intval($_POST["id"] ?? 0);
  $resuelta = intval($_POST["resuelta"] ?? -1); // 0 o 1

  if ($id <= 0 || ($resuelta !== 0 && $resuelta !== 1)) {
    throw new Exception("Datos inválidos.");
  }

  $nombre = trim($_SESSION["nombre"]);

  if ($resuelta === 1) {
    $stmt = $mysqli->prepare("
      UPDATE notas_revision
      SET resuelta = 1, resuelta_en = NOW(), resuelta_por = ?
      WHERE id = ?
    ");
    $stmt->bind_param("si", $nombre, $id);
  } else {
    $stmt = $mysqli->prepare("
      UPDATE notas_revision
      SET resuelta = 0, resuelta_en = NULL, resuelta_por = NULL
      WHERE id = ?
    ");
    $stmt->bind_param("i", $id);
  }

  $stmt->execute();
  $stmt->close();

  echo json_encode(["ok"=>true,"msg"=>"Estado actualizado."]);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(["ok"=>false,"msg"=>"Error en servidor","detalle"=>$e->getMessage()]);
}
