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

  $creadoPor = trim($_SESSION["nombre"]); // 👈 nombre del usuario en sesión
  $fechaVencimiento = date("Y-m-d H:i:s", strtotime("+30 days"));

  // Generar folio único
  $folio = null;
  $cid = null;

  for ($i = 0; $i < 10; $i++) {
    $folioTmp = "SCL-" . random_int(100000, 999999);

    $stmt = $mysqli->prepare("
      INSERT INTO cuestionarios (folio, fecha_vencimiento, creado_por)
      VALUES (?, ?, ?)
    ");
    if (!$stmt) throw new Exception("Error prepare: " . $mysqli->error);

    $stmt->bind_param("sss", $folioTmp, $fechaVencimiento, $creadoPor);

    if ($stmt->execute()) {
      $folio = $folioTmp;
      $cid = $mysqli->insert_id;
      $stmt->close();
      break;
    }

    $stmt->close();

    // 1062 = folio duplicado, reintenta
    if ($mysqli->errno != 1062) {
      throw new Exception("Error al insertar: " . $mysqli->error);
    }
  }

  if (!$folio || !$cid) {
    throw new Exception("No se pudo generar un folio único.");
  }

  echo json_encode([
    "ok" => true,
    "cid" => $cid,
    "folio" => $folio
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    "ok" => false,
    "msg" => "Error en servidor",
    "detalle" => $e->getMessage()
  ], JSON_UNESCAPED_UNICODE);
}
