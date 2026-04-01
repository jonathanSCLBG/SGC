<?php
header("Content-Type: application/json; charset=utf-8");
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION["nombre"])) {
    http_response_code(401);
    echo json_encode([
        "ok" => false,
        "msg" => "No autorizado"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . "/conexion.php";

try {
    $mysqli = db();

    $tipoUsuario = $_SESSION["tipo_usuario"] ?? "";
    $usuarioId   = (int)($_SESSION["id"] ?? 0);

    // =========================================================
    // Si el usuario es revisor, solo verá sus cuestionarios
    // no eliminados.
    // Si no lo es, verá todos los no eliminados.
    // =========================================================
    if ($tipoUsuario === "revisor") {
        $sql = "
            SELECT 
                c.id,
                c.folio,
                c.fecha_creacion,
                c.fecha_vencimiento,
                c.estatus,
                c.estatus_validacion,
                c.creado_por,
                c.revisor_id,
                c.actualizado_por,
                c.actualizado_en,
                c.validado_por,
                c.validado_en,
                u.Nombre AS revisor_nombre
            FROM cuestionarios c
            LEFT JOIN usuarios u 
                ON u.id = c.revisor_id
            WHERE c.eliminado = 0
              AND c.revisor_id = ?
            ORDER BY c.id DESC
        ";

        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            throw new Exception("Error prepare: " . $mysqli->error);
        }

        $stmt->bind_param("i", $usuarioId);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $sql = "
            SELECT 
                c.id,
                c.folio,
                c.fecha_creacion,
                c.fecha_vencimiento,
                c.estatus,
                c.estatus_validacion,
                c.creado_por,
                c.revisor_id,
                c.actualizado_por,
                c.actualizado_en,
                c.validado_por,
                c.validado_en,
                u.Nombre AS revisor_nombre
            FROM cuestionarios c
            LEFT JOIN usuarios u 
                ON u.id = c.revisor_id
            WHERE c.eliminado = 0
            ORDER BY c.id DESC
        ";

        $result = $mysqli->query($sql);

        if (!$result) {
            throw new Exception("Error al listar cuestionarios: " . $mysqli->error);
        }
    }

    $cuestionarios = [];

    while ($row = $result->fetch_assoc()) {
        $cuestionarios[] = [
            "id"                 => (int)$row["id"],
            "folio"              => $row["folio"] ?? "",
            "fecha_creacion"     => $row["fecha_creacion"] ?? "",
            "fecha_vencimiento"  => $row["fecha_vencimiento"] ?? "",
            "estatus"            => $row["estatus"] ?? "",
            "estatus_validacion" => $row["estatus_validacion"] ?? "",
            "creado_por"         => $row["creado_por"] ?? "",
            "revisor_id"         => isset($row["revisor_id"]) ? (int)$row["revisor_id"] : 0,
            "revisor_nombre"     => $row["revisor_nombre"] ?? "",
            "actualizado_por"    => $row["actualizado_por"] ?? "",
            "actualizado_en"     => $row["actualizado_en"] ?? "",
            "validado_por"       => $row["validado_por"] ?? "",
            "validado_en"        => $row["validado_en"] ?? ""
        ];
    }

    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }

    echo json_encode([
        "ok" => true,
        "cuestionarios" => $cuestionarios
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "msg" => "Error de servidor",
        "detalle" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}