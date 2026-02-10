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

  $usuarioActual = trim($_SESSION["nombre"]); // 👈 ultimo modificador

  $cid     = intval($_POST["cid"] ?? 0);
  $folio   = trim($_POST["folio"] ?? "");
  $seccion = trim($_POST["seccion"] ?? "");

  $preguntas   = $_POST["pregunta"] ?? [];
  $respuestas  = $_POST["respuesta"] ?? [];
  $comentarios = $_POST["comentarios"] ?? [];

  // Si no viene cid, resolvemos por folio
  if ($cid <= 0) {
    if ($folio === "") throw new Exception("Falta cid o folio.");

    $stmtF = $mysqli->prepare("SELECT id FROM cuestionarios WHERE folio = ? LIMIT 1");
    if (!$stmtF) throw new Exception($mysqli->error);
    $stmtF->bind_param("s", $folio);
    $stmtF->execute();
    $row = $stmtF->get_result()->fetch_assoc();
    $stmtF->close();

    if (!$row) throw new Exception("No existe cuestionario con ese folio.");
    $cid = intval($row["id"]);
  }

  if ($seccion === "" || !is_array($preguntas) || count($preguntas) === 0) {
    throw new Exception("Datos incompletos (seccion/preguntas).");
  }

  // UPSERT (respuestas)
  $sql = "INSERT INTO cuestionario_respuestas
          (cuestionario_id, seccion, pregunta, respuesta, comentarios, nom_evidencia)
          VALUES (?, ?, ?, ?, ?, ?)
          ON DUPLICATE KEY UPDATE
            respuesta = VALUES(respuesta),
            comentarios = VALUES(comentarios),
            nom_evidencia = VALUES(nom_evidencia)";

  $stmt = $mysqli->prepare($sql);
  if (!$stmt) throw new Exception("Prepare failed: " . $mysqli->error);

  // Base de uploads
  $uploadsBaseAbs = __DIR__ . "/../uploads"; // ruta física
  $uploadsBaseRel = "uploads";               // ruta web relativa (para links)
  if (!is_dir($uploadsBaseAbs)) mkdir($uploadsBaseAbs, 0777, true);

  $guardadas = 0;
  $archivosGuardados = 0;

  for ($i = 0; $i < count($preguntas); $i++) {
    $p = (string)$preguntas[$i];
    $r = (string)($respuestas[$i] ?? "");
    $c = (string)($comentarios[$i] ?? "");

    $key = "evidencia_" . $p;

    // Carpeta por pregunta
    $dirAbs = $uploadsBaseAbs . "/cid_" . $cid . "/" . $seccion . "/pregunta_" . preg_replace('/\D/', '', $p);
    $dirRel = $uploadsBaseRel . "/cid_" . $cid . "/" . $seccion . "/pregunta_" . preg_replace('/\D/', '', $p);
    if (!is_dir($dirAbs)) mkdir($dirAbs, 0777, true);

    // 1) Traer evidencias previas (para no borrar)
    $prevEvidencias = [];
    $stmtPrev = $mysqli->prepare("
      SELECT nom_evidencia
      FROM cuestionario_respuestas
      WHERE cuestionario_id = ? AND seccion = ? AND pregunta = ?
      LIMIT 1
    ");
    if (!$stmtPrev) throw new Exception("Prepare prev failed: " . $mysqli->error);

    $stmtPrev->bind_param("iss", $cid, $seccion, $p);
    $stmtPrev->execute();
    $prevRow = $stmtPrev->get_result()->fetch_assoc();
    $stmtPrev->close();

    if ($prevRow && !empty($prevRow["nom_evidencia"])) {
      $tmp = json_decode($prevRow["nom_evidencia"], true);
      if (is_array($tmp)) $prevEvidencias = $tmp;
    }

    $evidencias = $prevEvidencias;

    // 2) Subir nuevas evidencias (si vienen) y agregarlas
    if (isset($_FILES[$key]) && is_array($_FILES[$key]["name"])) {
      $n = count($_FILES[$key]["name"]);

      for ($f = 0; $f < $n; $f++) {
        if ($_FILES[$key]["error"][$f] !== UPLOAD_ERR_OK) continue;

        $orig = basename($_FILES[$key]["name"][$f]); // nombre original
        $origSafe = preg_replace('/[^a-zA-Z0-9_\.\-]/', '_', $orig);

        $final = time() . "_" . $origSafe;

        $destAbs = $dirAbs . "/" . $final;
        $destRel = $dirRel . "/" . $final;

        if (move_uploaded_file($_FILES[$key]["tmp_name"][$f], $destAbs)) {
          $evidencias[] = [
            "name" => $orig,
            "url"  => $destRel
          ];
          $archivosGuardados++;
        }
      }
    }

    $nom_evidencia = json_encode($evidencias, JSON_UNESCAPED_UNICODE);

    $stmt->bind_param("isssss", $cid, $seccion, $p, $r, $c, $nom_evidencia);
    if (!$stmt->execute()) throw new Exception("Execute failed: " . $stmt->error);

    $guardadas++;
  }

  $stmt->close();

  // ✅ 3) Guardar último modificador + fecha (a nivel cuestionario)
  $upd = $mysqli->prepare("
    UPDATE cuestionarios
    SET actualizado_por = ?, actualizado_en = NOW()
    WHERE id = ?
  ");
  if (!$upd) throw new Exception("Prepare update failed: " . $mysqli->error);

  $upd->bind_param("si", $usuarioActual, $cid);
  $upd->execute();
  $upd->close();

  echo json_encode([
    "ok" => true,
    "msg" => "Guardado: $guardadas preguntas. Archivos: $archivosGuardados",
    "cid" => $cid,
    "seccion" => $seccion,
    "actualizado_por" => $usuarioActual
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    "ok" => false,
    "msg" => "Error en servidor",
    "detalle" => $e->getMessage()
  ], JSON_UNESCAPED_UNICODE);
}
