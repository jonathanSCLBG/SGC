<?php
header("Content-Type: application/json; charset=utf-8");
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/conexion.php";

try {
  $mysqli = db();

  $cid     = intval($_GET["cid"] ?? 0);
  $folio   = trim($_GET["folio"] ?? "");
  $seccion = trim($_GET["seccion"] ?? "");

  if ($cid <= 0) {
    if ($folio === "") throw new Exception("Falta cid o folio.");

    $stmt = $mysqli->prepare("SELECT id FROM cuestionarios WHERE folio = ? LIMIT 1");
    if (!$stmt) throw new Exception($mysqli->error);
    $stmt->bind_param("s", $folio);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) throw new Exception("No existe cuestionario con ese folio.");
    $cid = intval($row["id"]);
  }

  if ($seccion === "") throw new Exception("Falta seccion.");

  $stmt = $mysqli->prepare("
    SELECT pregunta, respuesta, comentarios, nom_evidencia
    FROM cuestionario_respuestas
    WHERE cuestionario_id = ? AND seccion = ?
  ");
  if (!$stmt) throw new Exception($mysqli->error);

  $stmt->bind_param("is", $cid, $seccion);
  $stmt->execute();
  $res = $stmt->get_result();

  $data = [];
  while ($r = $res->fetch_assoc()) {
    // nom_evidencia ya es JSON string, lo mandamos tal cual
    $data[] = [
      "pregunta" => (string)$r["pregunta"],
      "respuesta" => (string)$r["respuesta"],
      "comentarios" => (string)$r["comentarios"],
      "nom_evidencia" => $r["nom_evidencia"] // JSON string
    ];
  }
  $stmt->close();

  echo json_encode([
    "ok" => true,
    "cid" => $cid,
    "seccion" => $seccion,
    "respuestas" => $data
  ]);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    "ok" => false,
    "msg" => "Error en servidor",
    "detalle" => $e->getMessage()
  ]);
}
