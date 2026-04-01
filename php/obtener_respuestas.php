<?php
header("Content-Type: application/json; charset=utf-8");
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/conexion.php";

try {
  $mysqli = db();

  $cid    = intval($_GET["cid"] ?? 0);
  $folio  = trim($_GET["folio"] ?? "");
  $seccion = trim($_GET["seccion"] ?? "");

  if ($cid <= 0) {
    if ($folio === "") throw new Exception("Falta cid o folio.");

    $stmt = $mysqli->prepare("SELECT id FROM cuestionarios WHERE folio = ? LIMIT 1");
    if (!$stmt) throw new Exception("Prepare folio: " . $mysqli->error);

    $stmt->bind_param("s", $folio);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();

    if (!$row) throw new Exception("No existe cuestionario con ese folio.");
    $cid = intval($row["id"]);
  }

  if ($seccion === "") throw new Exception("Falta seccion.");

  // Traemos las respuestas guardadas + evidencias + validada (columna nueva)
  // Ajusta nombres de columnas si los tienes distintos.
  $sql = "
    SELECT
      cuestionario_id,
      seccion,
      pregunta,
      respuesta,
      comentarios,
      nom_evidencia,
      IFNULL(validada,0) AS validada
    FROM cuestionario_respuestas
    WHERE cuestionario_id = ?
      AND seccion = ?
    ORDER BY CAST(pregunta AS UNSIGNED) ASC
  ";

  $stmt = $mysqli->prepare($sql);
  if (!$stmt) throw new Exception("Prepare respuestas: " . $mysqli->error);

  $stmt->bind_param("is", $cid, $seccion);
  $stmt->execute();
  $result = $stmt->get_result();

  $rows = [];
  while ($r = $result->fetch_assoc()) {
    // Si todavía guardabas evidencias como "a|b|c", aquí lo convertimos a JSON
    // Pero si ya guardas JSON, lo dejamos intacto.
    $nom = (string)($r["nom_evidencia"] ?? "");
    $nomTrim = trim($nom);

    if ($nomTrim !== "" && $nomTrim[0] !== "[") {
      // Formato legacy "a|b|c" -> lo convertimos a JSON con url completa relativa
      $parts = array_filter(explode("|", $nomTrim));
      $arr = [];
      foreach ($parts as $name) {
        $arr[] = ["name" => $name, "url" => $name];
      }
      $r["nom_evidencia"] = json_encode($arr, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    $rows[] = $r;
  }

  echo json_encode([
    "ok" => true,
    "cid" => $cid,
    "seccion" => $seccion,
    "respuestas" => $rows
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    "ok" => false,
    "msg" => "Error en servidor",
    "detalle" => $e->getMessage()
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}