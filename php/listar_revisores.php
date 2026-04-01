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

    // AJUSTA "email" si tu columna se llama distinto
    $sql = "
        SELECT id, Nombre, Usuario, correo
        FROM usuarios
        WHERE LOWER(tipo_usuario) = 'revisor'
        ORDER BY Nombre ASC
    ";

    $result = $mysqli->query($sql);

    if (!$result) {
        throw new Exception("Error al consultar revisores: " . $mysqli->error);
    }

    $revisores = [];

    while ($row = $result->fetch_assoc()) {
        $revisores[] = [
            "id"     => (int)$row["id"],
            "nombre" => $row["Nombre"] ?? "",
            "usuario"=> $row["Usuario"] ?? "",
            "email"  => $row["correo"] ?? ""
        ];
    }

    echo json_encode([
        "ok" => true,
        "revisores" => $revisores
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "msg" => "Error de servidor",
        "detalle" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}