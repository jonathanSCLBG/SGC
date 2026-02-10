<?php
header("Content-Type: application/json; charset=utf-8");
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require __DIR__ . '/conexion.php';

try {
    // ===== Validar sesión
    if (!isset($_SESSION['nombre'])) {
        throw new Exception("Sesión no válida.");
    }

    $revisor_nombre = trim($_SESSION['nombre']);

    // ===== Datos POST
    $cid      = intval($_POST['cid'] ?? 0);
    $seccion  = trim($_POST['seccion'] ?? '');
    $pregunta = trim($_POST['pregunta'] ?? '');
    $nota     = trim($_POST['nota'] ?? '');

    if ($cid <= 0 || $seccion === '' || $pregunta === '' || $nota === '') {
        throw new Exception("Datos incompletos.");
    }

    $mysqli = db();

    // ===== Insertar nota
    $sql = "
        INSERT INTO notas_revision
        (cuestionario_id, seccion, pregunta, nota, revisor_nombre)
        VALUES (?, ?, ?, ?, ?)
    ";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $mysqli->error);
    }

    $stmt->bind_param(
        "issss",
        $cid,
        $seccion,
        $pregunta,
        $nota,
        $revisor_nombre
    );

    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    echo json_encode([
        "ok"  => true,
        "msg" => "Nota guardada correctamente."
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "ok"      => false,
        "msg"     => "Error en servidor",
        "detalle" => $e->getMessage()
    ]);
}
