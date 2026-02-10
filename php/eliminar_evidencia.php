<?php
header("Content-Type: application/json; charset=utf-8");
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/conexion.php";

function out($ok, $msg, $extra = []) {
  echo json_encode(array_merge(["ok"=>$ok, "msg"=>$msg], $extra), JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $mysqli = db();

  $cid     = intval($_POST["cid"] ?? 0);
  $folio   = trim($_POST["folio"] ?? "");
  $seccion = trim($_POST["seccion"] ?? "");
  $pregunta = trim($_POST["pregunta"] ?? "");
  $url     = trim($_POST["url"] ?? "");  // uploads/cid_7/.../archivo.zip

  if ($cid <= 0) {
    if ($folio === "") out(false, "Falta cid o folio.");
    $stmtF = $mysqli->prepare("SELECT id FROM cuestionarios WHERE folio = ? LIMIT 1");
    $stmtF->bind_param("s", $folio);
    $stmtF->execute();
    $row = $stmtF->get_result()->fetch_assoc();
    $stmtF->close();
    if (!$row) out(false, "No existe cuestionario con ese folio.");
    $cid = intval($row["id"]);
  }

  if ($seccion === "" || $pregunta === "" || $url === "") {
    out(false, "Faltan datos (seccion, pregunta o url).");
  }

  // Seguridad: solo permitimos borrar cosas dentro de /uploads/
  // (evita que alguien intente borrar ../../algo)
  if (!preg_match('#^uploads/#', $url)) {
    out(false, "Ruta inválida.");
  }

  // 1) Traer evidencias actuales
  $stmt = $mysqli->prepare("
    SELECT nom_evidencia
    FROM cuestionario_respuestas
    WHERE cuestionario_id = ? AND seccion = ? AND pregunta = ?
    LIMIT 1
  ");
  $stmt->bind_param("iss", $cid, $seccion, $pregunta);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$row) out(false, "No existe esa pregunta guardada.");
  $json = $row["nom_evidencia"] ?? "";

  $arr = [];
  if ($json !== "") {
    $tmp = json_decode($json, true);
    if (is_array($tmp)) $arr = $tmp;
  }

  if (!is_array($arr) || count($arr) === 0) {
    out(false, "No hay evidencias para eliminar.");
  }

  // 2) Filtrar (quitar la evidencia por url)
  $antes = count($arr);
  $arr2 = array_values(array_filter($arr, function($e) use ($url) {
    return isset($e["url"]) ? $e["url"] !== $url : true;
  }));
  $despues = count($arr2);

  if ($antes === $despues) {
    out(false, "No se encontró esa evidencia en la BD.");
  }

  // 3) Actualizar BD
  $nuevoJson = json_encode($arr2, JSON_UNESCAPED_UNICODE);
  $stmtU = $mysqli->prepare("
    UPDATE cuestionario_respuestas
    SET nom_evidencia = ?
    WHERE cuestionario_id = ? AND seccion = ? AND pregunta = ?
  ");
  $stmtU->bind_param("siss", $nuevoJson, $cid, $seccion, $pregunta);
  $stmtU->execute();
  $stmtU->close();

  // 4) Borrar archivo físico
  // url es tipo uploads/..., el archivo real está en ../uploads/...
  $abs = realpath(__DIR__ . "/..") . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $url);

  // Seguridad extra: que efectivamente esté dentro de la carpeta uploads del proyecto
  $uploadsAbs = realpath(__DIR__ . "/../uploads");
  $absReal = realpath($abs);

  $borrado = false;
  if ($absReal && $uploadsAbs && strpos($absReal, $uploadsAbs) === 0) {
    if (is_file($absReal)) {
      $borrado = @unlink($absReal);
    }
  }

  out(true, "Evidencia eliminada.", [
    "cid" => $cid,
    "seccion" => $seccion,
    "pregunta" => $pregunta,
    "archivo_borrado" => $borrado
  ]);

} catch (Throwable $e) {
  http_response_code(500);
  out(false, "Error en servidor", ["detalle" => $e->getMessage()]);
}
