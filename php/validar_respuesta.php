<?php
header("Content-Type: application/json; charset=utf-8");
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . "/conexion.php";

try {
  // Si quieres forzar sesión:
  if (!isset($_SESSION["id"])) {
    http_response_code(401);
    echo json_encode(["ok" => false, "msg" => "Sin sesión"]);
    exit;
  }

  // Si quieres forzar rol revisor:
  $tipo = strtolower($_SESSION["tipo_usuario"] ?? "");
  if ($tipo !== "revisor") {
    http_response_code(403);
    echo json_encode(["ok" => false, "msg" => "No autorizado"]);
    exit;
  }

  $mysqli = db();

  $cid      = intval($_POST["cid"] ?? 0);
  $seccion  = trim($_POST["seccion"] ?? "");
  $pregunta = trim($_POST["pregunta"] ?? "");

  // 1 = validar, 0 = desvalidar
  // Si no lo mandas, por default validamos
  $validada = isset($_POST["validada"]) ? intval($_POST["validada"]) : 1;

  if ($cid <= 0 || $seccion === "" || $pregunta === "") {
    throw new Exception("Faltan datos (cid, seccion, pregunta).");
  }
  if (!in_array($validada, [0, 1], true)) {
    throw new Exception("Valor inválido en 'validada'.");
  }

  // ============================================================
  // 1) Si vamos a VALIDAR (validada=1), obligamos que no haya notas pendientes
  // ============================================================
  if ($validada === 1) {
    // ⚠️ Ajusta si tu tabla de notas se llama diferente
    $stmt = $mysqli->prepare("
      SELECT COUNT(*) AS pendientes
      FROM notas_revision
      WHERE cuestionario_id = ?
        AND seccion = ?
        AND pregunta = ?
        AND IFNULL(resuelta,0) = 0
    ");
    if (!$stmt) throw new Exception("Prepare pendientes: " . $mysqli->error);

    $stmt->bind_param("iss", $cid, $seccion, $pregunta);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $pendientes = intval($row["pendientes"] ?? 0);

    if ($pendientes > 0) {
      echo json_encode([
        "ok" => false,
        "msg" => "No se puede validar",
        "detalle" => "Aún hay notas pendientes en esta pregunta."
      ]);
      exit;
    }
  }

  // ============================================================
  // 2) Primero verificamos si existe la respuesta (fila) y su estado actual
  // ============================================================
  $stmt = $mysqli->prepare("
    SELECT COALESCE(validada,0) AS validada
    FROM cuestionario_respuestas
    WHERE cuestionario_id = ?
      AND seccion = ?
      AND pregunta = ?
    LIMIT 1
  ");
  if (!$stmt) throw new Exception("Prepare select: " . $mysqli->error);

  $stmt->bind_param("iss", $cid, $seccion, $pregunta);
  $stmt->execute();
  $actual = $stmt->get_result()->fetch_assoc();

  if (!$actual) {
    echo json_encode([
      "ok" => false,
      "msg" => "No existe respuesta",
      "detalle" => "No existe esa pregunta en cuestionario_respuestas (primero debe guardarse la sección)."
    ]);
    exit;
  }

  $estadoActual = intval($actual["validada"] ?? 0);

  // Si ya estaba en el mismo estado, respondemos OK (no es error)
  if ($estadoActual === $validada) {
    echo json_encode([
      "ok" => true,
      "msg" => ($validada === 1) ? "Ya estaba validada ✅" : "Ya estaba desvalidada ↩️",
      "cid" => $cid,
      "seccion" => $seccion,
      "pregunta" => $pregunta,
      "validada" => $validada
    ]);
    exit;
  }

  // ============================================================
  // 3) Ahora sí: UPDATE correcto (aquí estaba tu bug)
  // ============================================================
  $stmt = $mysqli->prepare("
    UPDATE cuestionario_respuestas
    SET validada = ?
    WHERE cuestionario_id = ?
      AND seccion = ?
      AND pregunta = ?
    LIMIT 1
  ");
  if (!$stmt) throw new Exception("Prepare update: " . $mysqli->error);

  // OJO: son 4 parámetros: (validada int) (cid int) (seccion string) (pregunta string)
  $stmt->bind_param("iiss", $validada, $cid, $seccion, $pregunta);
  $stmt->execute();

  // Si por alguna razón no afectó filas, no lo damos por muerto: volvemos a leer
  $stmt = $mysqli->prepare("
    SELECT COALESCE(validada,0) AS validada
    FROM cuestionario_respuestas
    WHERE cuestionario_id = ?
      AND seccion = ?
      AND pregunta = ?
    LIMIT 1
  ");
  if (!$stmt) throw new Exception("Prepare recheck: " . $mysqli->error);

  $stmt->bind_param("iss", $cid, $seccion, $pregunta);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $nuevo = intval($row["validada"] ?? 0);

  if ($nuevo !== $validada) {
    echo json_encode([
      "ok" => false,
      "msg" => "No se actualizó",
      "detalle" => "Se intentó actualizar pero el valor no cambió. Revisa si la columna 'validada' existe y permite escritura."
    ]);
    exit;
  }

  echo json_encode([
    "ok" => true,
    "msg" => ($validada === 1) ? "Respuesta validada ✅" : "Validación removida ↩️",
    "cid" => $cid,
    "seccion" => $seccion,
    "pregunta" => $pregunta,
    "validada" => $validada
  ]);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    "ok" => false,
    "msg" => "Error en servidor",
    "detalle" => $e->getMessage()
  ]);
}
