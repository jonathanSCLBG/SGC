<?php
header("Content-Type: application/json; charset=utf-8");
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/conexion.php";
session_start();

// Si no hay sesión activa, no dejamos consultar pendientes
if (!isset($_SESSION["nombre"])) {
  http_response_code(401);
  echo json_encode(["ok" => false, "msg" => "No autorizado"], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $mysqli = db();

  // cid del cuestionario
  $cid = intval($_GET["cid"] ?? 0);

  // si viene "seccion", regresamos pendientes por pregunta de esa sección
  // si no viene, regresamos pendientes por sección (todas)
  $seccion = trim($_GET["seccion"] ?? "");

  if ($cid <= 0) {
    throw new Exception("Falta cid.");
  }

  // ==========================
  //  CASO A: Pendientes por PREGUNTA (de una sección específica)
  //  GET: ?cid=...&seccion=pagina_1
  // ==========================
  if ($seccion !== "") {
    $stmt = $mysqli->prepare("
      SELECT pregunta, COUNT(*) AS pendientes
      FROM notas_revision
      WHERE cuestionario_id = ? AND seccion = ? AND resuelta = 0
      GROUP BY pregunta
    ");
    $stmt->bind_param("is", $cid, $seccion);
    $stmt->execute();
    $res = $stmt->get_result();

    $pendientes_por_pregunta = [];
    while ($r = $res->fetch_assoc()) {
      $pendientes_por_pregunta[(string)$r["pregunta"]] = intval($r["pendientes"]);
    }
    $stmt->close();

    // Total general SOLO de esa sección (sumamos pendientes por pregunta)
    $total_pendientes = 0;
    foreach ($pendientes_por_pregunta as $cnt) {
      $total_pendientes += (int)$cnt;
    }

    echo json_encode([
      "ok" => true,
      "cid" => $cid,
      "seccion" => $seccion,
      "pendientes_por_pregunta" => $pendientes_por_pregunta,
      "total_pendientes" => $total_pendientes
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // ==========================
  //  CASO B: Pendientes por SECCION (todas)
  //  GET: ?cid=...
  // ==========================
  $stmt = $mysqli->prepare("
    SELECT seccion, COUNT(*) AS pendientes
    FROM notas_revision
    WHERE cuestionario_id = ? AND resuelta = 0
    GROUP BY seccion
  ");
  $stmt->bind_param("i", $cid);
  $stmt->execute();
  $res = $stmt->get_result();

  $pendientes_por_seccion = [];
  while ($r = $res->fetch_assoc()) {
    $pendientes_por_seccion[(string)$r["seccion"]] = intval($r["pendientes"]);
  }
  $stmt->close();

  // Total general de pendientes (sumamos pendientes por sección)
  $total_pendientes = 0;
  foreach ($pendientes_por_seccion as $cnt) {
    $total_pendientes += (int)$cnt;
  }

  echo json_encode([
    "ok" => true,
    "cid" => $cid,
    "pendientes_por_seccion" => $pendientes_por_seccion,
    "total_pendientes" => $total_pendientes
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    "ok" => false,
    "msg" => "Error en servidor",
    "detalle" => $e->getMessage()
  ], JSON_UNESCAPED_UNICODE);
}
