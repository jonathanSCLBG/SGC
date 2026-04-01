<?php
header("Content-Type: application/json; charset=utf-8");
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/conexion.php";

try {
    $mysqli = db();

    $sql = "
        SELECT
            c.id,
            c.folio,
            c.fecha_creacion,
            c.fecha_vencimiento,
            c.estatus,
            c.creado_por,
            c.actualizado_por,
            c.actualizado_en
        FROM cuestionarios c
        WHERE c.eliminado = 0
          AND c.estatus_validacion = 'validado'
        ORDER BY c.actualizado_en DESC, c.id DESC
        LIMIT 200
    ";

    $res = $mysqli->query($sql);

    if (!$res) {
        throw new Exception("Error en la consulta SQL: " . $mysqli->error);
    }

    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }

    echo json_encode([
        "ok" => true,
        "cuestionarios" => $rows
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "msg" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>