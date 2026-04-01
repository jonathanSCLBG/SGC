<?php
header("Content-Type: application/json; charset=utf-8");
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require __DIR__ . '/conexion.php';

// =========================
// PHPMailer (manual, sin composer)
// =========================
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . "/PHPMailer/src/PHPMailer.php";
require_once __DIR__ . "/PHPMailer/src/SMTP.php";
require_once __DIR__ . "/PHPMailer/src/Exception.php";

/* =========================================================
   HELPERS
========================================================= */
function columnaExiste(mysqli $mysqli, string $tabla, string $columna): bool {
    $tabla = $mysqli->real_escape_string($tabla);
    $columna = $mysqli->real_escape_string($columna);

    $sql = "SHOW COLUMNS FROM `$tabla` LIKE '$columna'";
    $res = $mysqli->query($sql);

    return $res && $res->num_rows > 0;
}

function obtenerCorreoPorNombre(mysqli $mysqli, string $nombre): string {
    $nombre = trim($nombre);
    if ($nombre === '') return '';

    $stmt = $mysqli->prepare("
        SELECT correo
        FROM usuarios
        WHERE Nombre = ?
        LIMIT 1
    ");
    if (!$stmt) return '';

    $stmt->bind_param("s", $nombre);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return trim($row['correo'] ?? '');
}

function obtenerUsuarioPorId(mysqli $mysqli, int $id): array {
    if ($id <= 0) {
        return ["nombre" => "", "correo" => ""];
    }

    $stmt = $mysqli->prepare("
        SELECT Nombre, correo
        FROM usuarios
        WHERE id = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return ["nombre" => "", "correo" => ""];
    }

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return [
        "nombre" => trim($row['Nombre'] ?? ''),
        "correo" => trim($row['correo'] ?? '')
    ];
}

function obtenerUsuarioPorNombre(mysqli $mysqli, string $nombre): array {
    $nombre = trim($nombre);
    if ($nombre === '') {
        return ["nombre" => "", "correo" => ""];
    }

    $stmt = $mysqli->prepare("
        SELECT Nombre, correo
        FROM usuarios
        WHERE Nombre = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return ["nombre" => "", "correo" => ""];
    }

    $stmt->bind_param("s", $nombre);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return [
        "nombre" => trim($row['Nombre'] ?? $nombre),
        "correo" => trim($row['correo'] ?? '')
    ];
}

try {
    /* =========================================================
       1) Validar sesión
    ========================================================= */
    if (!isset($_SESSION['nombre'])) {
        throw new Exception("Sesión no válida.");
    }

    $validador_nombre = trim($_SESSION['nombre']);

    /* =========================================================
       2) Datos POST
    ========================================================= */
    $cid      = intval($_POST['cid'] ?? 0);
    $seccion  = trim($_POST['seccion'] ?? '');
    $pregunta = trim($_POST['pregunta'] ?? '');
    $nota     = trim($_POST['nota'] ?? '');

    if ($cid <= 0 || $seccion === '' || $pregunta === '' || $nota === '') {
        throw new Exception("Datos incompletos.");
    }

    $mysqli = db();

    $seccionNum = str_replace("pagina_", "", $seccion);
    $romanos = [
        "1" => "I",
        "2" => "II",
        "3" => "III",
        "4" => "IV",
        "5" => "V",
        "6" => "VI",
        "7" => "VII",
        "8" => "VIII"
    ];
    $seccionRomana = $romanos[$seccionNum] ?? $seccionNum;

    /* =========================================================
       3) Guardar nota
    ========================================================= */
    $sqlInsert = "
        INSERT INTO notas_revision
        (cuestionario_id, seccion, pregunta, nota, revisor_nombre)
        VALUES (?, ?, ?, ?, ?)
    ";

    $stmt = $mysqli->prepare($sqlInsert);
    if (!$stmt) {
        throw new Exception("Prepare failed (notas_revision): " . $mysqli->error);
    }

    // Se conserva revisor_nombre por compatibilidad con tu tabla actual
    $stmt->bind_param("issss", $cid, $seccion, $pregunta, $nota, $validador_nombre);

    if (!$stmt->execute()) {
        throw new Exception("Execute failed (notas_revision): " . $stmt->error);
    }
    $stmt->close();

    /* =========================================================
       4) Leer cuestionario sin asumir columnas inexistentes
    ========================================================= */
    $columnasBase = ["id", "folio"];
    $columnasOpcionales = [
        "actualizado_por",
        "actualizado_en",
        "creado_por",
        "revisor_id",
        "revisor_nombre",
        "revisor",
        "asignado_revisor",
        "revisado_por"
    ];

    $selectCols = [];
    foreach ($columnasBase as $c) {
        if (columnaExiste($mysqli, "cuestionarios", $c)) {
            $selectCols[] = $c;
        }
    }

    foreach ($columnasOpcionales as $c) {
        if (columnaExiste($mysqli, "cuestionarios", $c)) {
            $selectCols[] = $c;
        }
    }

    if (empty($selectCols)) {
        throw new Exception("No se pudieron detectar columnas en la tabla cuestionarios.");
    }

    $sqlCuest = "SELECT " . implode(", ", $selectCols) . " FROM cuestionarios WHERE id = ? LIMIT 1";
    $stmtQ = $mysqli->prepare($sqlCuest);
    if (!$stmtQ) {
        throw new Exception("Prepare failed (cuestionarios): " . $mysqli->error);
    }

    $stmtQ->bind_param("i", $cid);
    $stmtQ->execute();
    $cuestionario = $stmtQ->get_result()->fetch_assoc();
    $stmtQ->close();

    if (!$cuestionario) {
        throw new Exception("No se encontró el cuestionario.");
    }

    $folio = trim($cuestionario["folio"] ?? ("CID-" . $cid));

    /* =========================================================
       5) Preparador
       Prioridad:
       - actualizado_por
       - creado_por
    ========================================================= */
    $preparadorNombre = trim($cuestionario["actualizado_por"] ?? '');
    if ($preparadorNombre === '') {
        $preparadorNombre = trim($cuestionario["creado_por"] ?? '');
    }

    $preparadorInfo = obtenerUsuarioPorNombre($mysqli, $preparadorNombre);
    $preparadorCorreo = $preparadorInfo["correo"];
    $preparadorNombreFinal = $preparadorInfo["nombre"] !== ''
        ? $preparadorInfo["nombre"]
        : ($preparadorNombre !== '' ? $preparadorNombre : 'Preparador');

    /* =========================================================
       6) Revisor
       Intentamos varias posibilidades según cómo esté tu tabla
    ========================================================= */
    $revisorNombre = '';
    $revisorCorreo = '';

    // Caso 1: existe id_revisor
    if (isset($cuestionario["revisor_id"]) && intval($cuestionario["revisor_id"]) > 0) {
        $rev = obtenerUsuarioPorId($mysqli, intval($cuestionario["revisor_id"]));
        $revisorNombre = $rev["nombre"];
        $revisorCorreo = $rev["correo"];
    }

    // Caso 2: existe nombre directo en alguna columna textual
    if ($revisorCorreo === '' && $revisorNombre === '') {
        $camposNombreRevisor = ["revisor_nombre", "revisor", "asignado_revisor", "revisado_por"];

        foreach ($camposNombreRevisor as $campo) {
            if (!empty($cuestionario[$campo])) {
                $rev = obtenerUsuarioPorNombre($mysqli, trim($cuestionario[$campo]));
                $revisorNombre = $rev["nombre"] !== '' ? $rev["nombre"] : trim($cuestionario[$campo]);
                $revisorCorreo = $rev["correo"];
                break;
            }
        }
    }

    /* =========================================================
       7) Destinatarios sin duplicados
    ========================================================= */
    $destinatarios = [];

    if ($preparadorCorreo !== '') {
        $destinatarios[strtolower($preparadorCorreo)] = [
            "email" => $preparadorCorreo,
            "nombre" => $preparadorNombreFinal,
            "rol" => "Preparador"
        ];
    }

    if ($revisorCorreo !== '') {
        $destinatarios[strtolower($revisorCorreo)] = [
            "email" => $revisorCorreo,
            "nombre" => ($revisorNombre !== '' ? $revisorNombre : 'Revisor'),
            "rol" => "Revisor"
        ];
    }

    /* =========================================================
       8) Enviar correo
    ========================================================= */
    $email_ok = false;
    $email_msg = "No se envió correo (no se encontró email del preparador ni del revisor).";
    $notificados = [];

    if (count($destinatarios) > 0) {
        try {
            $smtpUser = "sgc_scl@sclconsultores.com.mx";
            $smtpPass = "L(769676559030uh";

            $link = "http://localhost/Interfaz_final/login.html";

            $mail = new PHPMailer(true);
            $mail->CharSet = "UTF-8";

            $mail->isSMTP();
            $mail->Host       = "smtp.office365.com";
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtpUser;
            $mail->Password   = $smtpPass;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom($smtpUser, "SGC - Notificaciones");

            foreach ($destinatarios as $d) {
                $mail->addAddress($d["email"], $d["nombre"]);
                $notificados[] = $d["email"];
            }

            $seccionSafe   = htmlspecialchars($seccionRomana, ENT_QUOTES, "UTF-8");
            $pregSafe      = htmlspecialchars($pregunta, ENT_QUOTES, "UTF-8");
            $notaSafe      = nl2br(htmlspecialchars($nota, ENT_QUOTES, "UTF-8"));
            $folioSafe     = htmlspecialchars($folio, ENT_QUOTES, "UTF-8");
            $validadorSafe = htmlspecialchars($validador_nombre, ENT_QUOTES, "UTF-8");
            $prepSafe      = htmlspecialchars($preparadorNombreFinal, ENT_QUOTES, "UTF-8");
            $revSafe       = htmlspecialchars($revisorNombre !== '' ? $revisorNombre : 'No asignado', ENT_QUOTES, "UTF-8");
            $fechaSafe     = htmlspecialchars(date("Y-m-d H:i:s"), ENT_QUOTES, "UTF-8");

            $mail->isHTML(true);
            $mail->Subject = "SGC: Nueva nota del validador en sección {$seccionSafe} · Pregunta {$pregSafe} ({$folioSafe})";

            $mail->Body = "
            <!DOCTYPE html>
            <html lang='es'>
            <body style='margin:0; padding:0; background:#f5f5f5; font-family:Segoe UI, Tahoma, Geneva, Verdana, sans-serif;'>
              <table width='100%' cellpadding='0' cellspacing='0' style='background:#f5f5f5; padding:20px 0;'>
                <tr>
                  <td align='center'>
                    <table width='770' cellpadding='0' cellspacing='0' style='max-width:770px; width:90%; background:#ffffff;'>

                      <tr>
                        <td style='background:#28334f; padding:15px 20px; color:#ffffff;'>
                          <table width='100%' cellpadding='0' cellspacing='0'>
                            <tr>
                              <td style='vertical-align:middle; width:70px;'>
                                <img src='https://sclconsultores.com.mx/imagenesRegistros/logo.svg'
                                     height='35'
                                     style='background:#ffffff; border-radius:8px; padding:5px 10px;' />
                              </td>
                              <td style='vertical-align:middle; padding-left:10px;'>
                                <h1 style='margin:0; font-size:20px; color:#ffffff;'>SGC - Se ha generado una nueva nota del validador</h1>
                              </td>
                            </tr>
                          </table>
                        </td>
                      </tr>

                      <tr>
                        <td style='padding:20px; color:#30305c;'>
                          <p style='margin:0 0 12px 0;'>
                            El validador ha registrado una nueva observación que requiere revisión y seguimiento.
                          </p>

                          <p style='margin:0 0 8px 0;'><strong>Cuestionario:</strong> {$folioSafe}</p>
                          <p style='margin:0 0 8px 0;'><strong>Sección:</strong> {$seccionSafe}</p>
                          <p style='margin:0 0 8px 0;'><strong>Pregunta:</strong> {$pregSafe}</p>
                          <p style='margin:0 0 8px 0;'><strong>Validador:</strong> {$validadorSafe}</p>
                          <p style='margin:0 0 8px 0;'><strong>Preparador:</strong> {$prepSafe}</p>
                          <p style='margin:0 0 8px 0;'><strong>Revisor:</strong> {$revSafe}</p>
                          <p style='margin:0 0 16px 0;'><strong>Fecha:</strong> {$fechaSafe}</p>

                          <div style='background:#f6f7fb; border:1px solid #e5e7eb; border-radius:10px; padding:12px 14px;'>
                            <div style='font-weight:700; margin-bottom:6px;'>Nota:</div>
                            <div style='color:#111827;'>{$notaSafe}</div>
                          </div>

                          <div style='margin-top:16px; text-align:right;'>
                            <a href='{$link}'
                               style='display:inline-block; padding:8px 18px; border-radius:20px; background:#28334f; color:#ffffff; text-decoration:none; border:2px solid #28334f;'>
                              Ir al SGC
                            </a>
                          </div>
                        </td>
                      </tr>

                      <tr>
                        <td align='center' style='padding:15px; font-size:12px; color:#6b6b6b;'>
                          Este es un aviso automático generado por el Sistema de Gestión de Calidad (SGC). Por favor, no responda a este mensaje.
                        </td>
                      </tr>

                    </table>
                  </td>
                </tr>
              </table>
            </body>
            </html>
            ";

            $mail->AltBody =
                "SGC - Nueva nota del validador\n\n" .
                "Cuestionario: {$folio}\n" .
                "Sección: {$seccionRomana}\n" .
                "Pregunta: {$pregunta}\n" .
                "Validador: {$validador_nombre}\n" .
                "Preparador: {$preparadorNombreFinal}\n" .
                "Revisor: " . ($revisorNombre !== '' ? $revisorNombre : 'No asignado') . "\n" .
                "Fecha: " . date("Y-m-d H:i:s") . "\n\n" .
                "Nota:\n{$nota}\n\n" .
                "Accede al sistema en: {$link}";

            $mail->send();

            $email_ok = true;
            $email_msg = "Correo enviado a: " . implode(", ", $notificados);
        } catch (Throwable $mailErr) {
            $email_ok = false;
            $email_msg = "Fallo correo: " . $mailErr->getMessage();
            error_log("SGC mail enviar_nota_validador error: " . $mailErr->getMessage());
        }
    }

    /* =========================================================
       9) Respuesta
    ========================================================= */
    echo json_encode([
        "ok" => true,
        "msg" => "Nota guardada correctamente.",
        "email_ok" => $email_ok,
        "email_msg" => $email_msg,
        "notificados" => $notificados,
        "debug" => [
            "preparador_nombre" => $preparadorNombreFinal,
            "preparador_correo" => $preparadorCorreo,
            "revisor_nombre" => $revisorNombre,
            "revisor_correo" => $revisorCorreo,
            "columnas_detectadas" => $selectCols
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "msg" => "Error en servidor",
        "detalle" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}