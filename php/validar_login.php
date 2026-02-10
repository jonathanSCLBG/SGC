<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require __DIR__ . '/conexion.php';

// Evitar acceso directo por GET
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../login.html");
    exit;
}

$usuario  = $_POST['usuario'] ?? '';
$password = $_POST['password'] ?? '';

if ($usuario === '' || $password === '') {
    header("Location: ../login.html");
    exit;
}

try {
    $mysqli = db();

    $sql = "
        SELECT id, Nombre, Usuario, tipo_usuario
        FROM usuarios
        WHERE Usuario = ?
          AND contrasena = ?
        LIMIT 1
    ";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new Exception("Error prepare: " . $mysqli->error);
    }

    $stmt->bind_param("ss", $usuario, $password);
    $stmt->execute();

    $result = $stmt->get_result();
    $datos  = $result->fetch_assoc();

    if (!$datos) {
        echo "<script>
            alert('Usuario o contraseña incorrectos');
            window.location.href = '../login.html';
        </script>";
        exit;
    }

   $_SESSION['id']           = $datos['id'];
   $_SESSION['user_id']      = $datos['id'];
   $_SESSION['nombre']       = $datos['Nombre'];
   $_SESSION['user_nombre']  = $datos['Nombre'];
   $_SESSION['usuario']      = $datos['Usuario'];
   $_SESSION['tipo_usuario'] = $datos['tipo_usuario'];


    if ($datos['tipo_usuario'] === 'preparador') {
        header("Location: ../index.html");
        exit;
    }

    if ($datos['tipo_usuario'] === 'revisor') {
        header("Location: ../index2.html");
        exit;
    }

    throw new Exception("Tipo de usuario no válido.");

} catch (Throwable $e) {
    die("Error en validar_login.php: " . $e->getMessage());
}
