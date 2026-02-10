<?php
header("Content-Type: application/json; charset=utf-8");
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION["id"])) {
  http_response_code(401);
  echo json_encode([
    "ok" => false,
    "msg" => "No autorizado"
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

echo json_encode([
  "ok" => true,
  "user_id" => $_SESSION["id"],
  "user_nombre" => $_SESSION["nombre"] ?? ($_SESSION["user_nombre"] ?? "Usuario"),
  "usuario" => $_SESSION["usuario"] ?? null,
  "tipo_usuario" => $_SESSION["tipo_usuario"] ?? null
], JSON_UNESCAPED_UNICODE);
