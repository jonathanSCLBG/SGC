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

function responder($ok, $mensaje = '', $extra = [])
{
    echo json_encode(array_merge([
        "ok" => $ok,
        "mensaje" => $mensaje
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

function limpiarCorreo($correo)
{
    $correo = trim((string)$correo);
    return filter_var($correo, FILTER_VALIDATE_EMAIL) ? $correo : '';
}

function agregarDestinatario(&$destinatarios, $correo, $nombre = '')
{
    $correo = limpiarCorreo($correo);
    if ($correo === '') return;

    $key = mb_strtolower($correo);
    if (!isset($destinatarios[$key])) {
        $destinatarios[$key] = [
            'correo' => $correo,
            'nombre' => trim((string)$nombre)
        ];
    }
}

function enviarCorreoNotaResuelta($destinatarios, $asunto, $bodyHtml, $altBody = '')
{
    if (empty($destinatarios)) {
        return [
            "ok" => false,
            "mensaje" => "No hay destinatarios válidos para el correo."
        ];
    }

    $mail = new PHPMailer(true);

    try {
        // =========================
        // CONFIGURA TU SMTP AQUÍ
        // =========================
        $mail->isSMTP();
        $mail->Host       = 'smtp.office365.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'sgc_scl@sclconsultores.com.mx';
        $mail->Password   = 'L(769676559030uh';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        // DEBUG SMTP opcional
        // $mail->SMTPDebug = 2;
        // $mail->Debugoutput = function($str, $level) { error_log("SMTP DEBUG: $str"); };

        $mail->setFrom('sgc_scl@sclconsultores.com.mx', 'SGC');

        foreach ($destinatarios as $d) {
            $mail->addAddress($d['correo'], $d['nombre']);
        }

        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body    = $bodyHtml;
        $mail->AltBody = $altBody !== '' ? $altBody : strip_tags(str_replace("<br>", "\n", $bodyHtml));

        $mail->send();

        return [
            "ok" => true,
            "mensaje" => "Correo enviado correctamente."
        ];
    } catch (Exception $e) {
        return [
            "ok" => false,
            "mensaje" => "No se pudo enviar el correo: " . $mail->ErrorInfo
        ];
    }
}

try {
    if (!isset($_SESSION['nombre'])) {
        throw new Exception("Sesión no válida.");
    }

    $usuarioSesion = trim((string)$_SESSION['nombre']);
    $notaId        = intval($_POST['id'] ?? 0);
    $resuelta      = intval($_POST['resuelta'] ?? -1);

    if ($notaId <= 0) {
        throw new Exception("ID de nota inválido.");
    }

    if ($resuelta !== 0 && $resuelta !== 1) {
        throw new Exception("Valor de resuelta inválido.");
    }

    $mysqli = db();
    $mysqli->set_charset("utf8mb4");

    // =========================
    // 1) OBTENER DATOS DE LA NOTA
    // =========================
    $sqlNota = "
        SELECT
            n.id,
            n.cuestionario_id AS cid,
            n.seccion,
            n.pregunta,
            n.nota,
            n.revisor_nombre AS autor_nota
        FROM notas_revision n
        WHERE n.id = ?
        LIMIT 1
    ";

    $stmtNota = $mysqli->prepare($sqlNota);
    if (!$stmtNota) {
        throw new Exception("Error preparando consulta de nota: " . $mysqli->error);
    }

    $stmtNota->bind_param("i", $notaId);
    $stmtNota->execute();
    $resNota = $stmtNota->get_result();
    $nota = $resNota->fetch_assoc();
    $stmtNota->close();

    if (!$nota) {
        throw new Exception("No se encontró la nota.");
    }

    // =========================
    // 2) ACTUALIZAR ESTATUS
    // =========================
    if ($resuelta === 1) {
        $sqlUpdate = "
            UPDATE notas_revision
            SET
                resuelta = 1,
                resuelta_por = ?,
                resuelta_en = NOW()
            WHERE id = ?
            LIMIT 1
        ";

        $stmtUpdate = $mysqli->prepare($sqlUpdate);
        if (!$stmtUpdate) {
            throw new Exception("Error preparando update: " . $mysqli->error);
        }

        $stmtUpdate->bind_param("si", $usuarioSesion, $notaId);
    } else {
        $sqlUpdate = "
            UPDATE notas_revision
            SET
                resuelta = 0,
                resuelta_por = NULL,
                resuelta_en = NULL
            WHERE id = ?
            LIMIT 1
        ";

        $stmtUpdate = $mysqli->prepare($sqlUpdate);
        if (!$stmtUpdate) {
            throw new Exception("Error preparando update: " . $mysqli->error);
        }

        $stmtUpdate->bind_param("i", $notaId);
    }

    if (!$stmtUpdate->execute()) {
        throw new Exception("No se pudo actualizar la nota: " . $stmtUpdate->error);
    }

    $stmtUpdate->close();

    // Si se marcó como pendiente, no manda correo
    if ($resuelta === 0) {
        responder(true, "La nota se marcó como pendiente.");
    }

    // =========================
    // 3) OBTENER AUTOR DE LA NOTA
    // =========================
    $autorNota = trim((string)$nota['autor_nota']);

    $sqlAutor = "
        SELECT
            id,
            Nombre,
            correo,
            tipo_usuario
        FROM usuarios
        WHERE Nombre = ?
        LIMIT 1
    ";

    $stmtAutor = $mysqli->prepare($sqlAutor);
    if (!$stmtAutor) {
        throw new Exception("Error preparando consulta del autor: " . $mysqli->error);
    }

    $stmtAutor->bind_param("s", $autorNota);
    $stmtAutor->execute();
    $resAutor = $stmtAutor->get_result();
    $autor = $resAutor->fetch_assoc();
    $stmtAutor->close();

    $tipoAutor   = mb_strtolower(trim((string)($autor['tipo_usuario'] ?? '')));
    $correoAutor = trim((string)($autor['correo'] ?? ''));
    $nombreAutor = trim((string)($autor['Nombre'] ?? $autorNota));

    // =========================
    // 4) SI LA NOTA NO ES DE VALIDADOR, NO MANDAR CORREO
    // =========================
    if ($tipoAutor !== 'validador') {
        responder(true, "La nota se marcó como resuelta, pero no se envió correo porque la nota no fue creada por un validador.", [
            "tipo_autor" => $tipoAutor,
            "autor_nota" => $autorNota,
            "correo_enviado" => false
        ]);
    }

    // =========================
    // 5) OBTENER DATOS DEL CUESTIONARIO
    // =========================
    $cid = intval($nota['cid']);

    $sqlCuestionario = "
        SELECT
            c.id,
            c.folio
        FROM cuestionarios c
        WHERE c.id = ?
        LIMIT 1
    ";

    $stmtCuestionario = $mysqli->prepare($sqlCuestionario);
    if (!$stmtCuestionario) {
        throw new Exception("Error preparando consulta del cuestionario: " . $mysqli->error);
    }

    $stmtCuestionario->bind_param("i", $cid);
    $stmtCuestionario->execute();
    $resCuestionario = $stmtCuestionario->get_result();
    $cuestionario = $resCuestionario->fetch_assoc();
    $stmtCuestionario->close();

    if (!$cuestionario) {
        throw new Exception("No se encontró el cuestionario relacionado con la nota.");
    }

    $folio     = trim((string)($cuestionario['folio'] ?? ''));
    $seccion   = trim((string)$nota['seccion']);
    $pregunta  = trim((string)$nota['pregunta']);
    $textoNota = trim((string)$nota['nota']);

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


    // =========================
    // 6) DESTINATARIO ÚNICO: EL VALIDADOR QUE CREÓ LA NOTA
    // =========================
    $destinatarios = [];
    agregarDestinatario($destinatarios, $correoAutor, $nombreAutor);

    if (empty($destinatarios)) {
        throw new Exception("La nota fue creada por un validador, pero ese usuario no tiene un correo válido.");
    }

    // =========================
    // 7) DATOS SEGUROS
    // =========================
    $folioSafe         = htmlspecialchars($folio);
    $seccionSafe       = htmlspecialchars($seccionRomana);
    $pregSafe          = htmlspecialchars($pregunta);
    $autorSafe         = htmlspecialchars($nombreAutor);
    $notaSafe          = nl2br(htmlspecialchars($textoNota));
    $usuarioSesionSafe = htmlspecialchars($usuarioSesion);

    // Ajusta la ruta real de tu sistema
    $link = "http://localhost/Interfaz_final/login.html";

    // =========================
    // 8) ASUNTO Y BODY
    // =========================
    $asunto = "SGC: Nota resuelta en {$folioSafe} (Sección {$seccionSafe} · Pregunta {$pregSafe})";

    $altBody = "Se ha marcado como resuelta una respuesta del cuestionario {$folio}. "
             . "Sección: {$seccion}. Pregunta: {$pregunta}. "
             . "La nota fue creada por el validador {$nombreAutor}. "
             . "Resuelta por: {$usuarioSesion}.";

    $bodyHtml = "
    <!DOCTYPE html>
        <html>
        <body style='margin:0; padding:0; background:#f5f5f5; font-family:Segoe UI, Tahoma, Geneva, Verdana, sans-serif;'>
            <table width='100%' cellpadding='0' cellspacing='0' style='background:#f5f5f5; padding:20px 0;'>
            <tr>
                <td align='center'>
                <table width='770' cellpadding='0' cellspacing='0' style='max-width:770px; width:90%; background:#ffffff;'>

                    <tr>
                    <td style='background:#28334f; padding:15px 20px; color:#ffffff;'>
                        <table width='100%'>
                        <tr>
                            <td style='vertical-align:middle;'>
                            <img src='https://sclconsultores.com.mx/imagenesRegistros/logo.svg' height='35'
                                style='background:#ffffff; border-radius:8px; padding:5px 10px;' />
                            </td>
                            <td style='vertical-align:middle; padding-left:10px;'>
                            <h1 style='margin:0; font-size:20px; color:#ffffff;'>SGC - Actualización de nota</h1>
                            </td>
                        </tr>
                        </table>
                    </td>
                    </tr>

                    <tr>
                    <td style='padding:20px; color:#30305c;'>
                        <p style='margin:0 0 8px 0;'>
                        Una nota previamente emitida ha sido atendida y está lista para su revalidación.
                        </p>

                        <p style='margin:0 0 8px 0;'><strong>Cuestionario:</strong> {$folioSafe}</p>
                        <p style='margin:0 0 8px 0;'><strong>Sección:</strong> {$seccionSafe}</p>
                        <p style='margin:0 0 8px 0;'><strong>Pregunta:</strong> {$pregSafe}</p>

                        <div style='background:#f6f7fb; border:1px solid #e5e7eb; border-radius:10px; padding:12px 14px;'>
                        <div style='font-weight:700; margin-bottom:6px;'>Nota de referencia:</div>
                        <div style='color:#111827;'>{$notaSafe}</div>
                        </div>

                        <div style='margin-top:14px; text-align:right;'>
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

    // =========================
    // 9) ENVIAR CORREO
    // =========================
    $resultadoCorreo = enviarCorreoNotaResuelta($destinatarios, $asunto, $bodyHtml, $altBody);

    responder(true, "La nota se marcó como resuelta y se envió correo al validador creador de la nota.", [
        "correo" => $resultadoCorreo,
        "tipo_autor" => $tipoAutor,
        "autor_nota" => $nombreAutor,
        "destinatarios" => array_values($destinatarios),
        "folio" => $folio
    ]);

} catch (Exception $e) {
    responder(false, $e->getMessage());
}