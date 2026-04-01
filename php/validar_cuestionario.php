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

try {
    // =====================================================
    // 1) VALIDAR SESIÓN
    // =====================================================
    if (!isset($_SESSION['id']) || !isset($_SESSION['nombre'])) {
        throw new Exception("Sesión no válida.");
    }

    if (($_SESSION['tipo_usuario'] ?? '') !== 'validador') {
        throw new Exception("No tienes permisos para validar.");
    }

    $validadorNombre = trim($_SESSION['nombre']);
    $id = intval($_POST['id'] ?? 0);

    if ($id <= 0) {
        throw new Exception("ID de cuestionario inválido.");
    }

    $mysqli = db();

    // =====================================================
    // 2) OBTENER INFO DEL CUESTIONARIO Y DEL REVISOR
    //    RELACIÓN CORRECTA:
    //    cuestionarios.revisor_id = usuarios.id
    // =====================================================
    $sqlInfo = "
        SELECT
            c.id,
            c.folio,
            c.revisor_id,
            c.estatus_validacion,
            u.Nombre AS revisor_nombre,
            u.correo AS revisor_correo
        FROM cuestionarios c
        LEFT JOIN usuarios u
            ON u.id = c.revisor_id
        WHERE c.id = ?
          AND c.eliminado = 0
        LIMIT 1
    ";

    $stmtInfo = $mysqli->prepare($sqlInfo);
    if (!$stmtInfo) {
        throw new Exception("Error prepare info: " . $mysqli->error);
    }

    $stmtInfo->bind_param("i", $id);

    if (!$stmtInfo->execute()) {
        throw new Exception("Error al consultar cuestionario: " . $stmtInfo->error);
    }

    $resultInfo = $stmtInfo->get_result();
    $info = $resultInfo->fetch_assoc();
    $stmtInfo->close();

    if (!$info) {
        throw new Exception("No se encontró el cuestionario.");
    }

    $folio = trim($info['folio'] ?? ('CID-' . $id));
    $revisorNombre = trim($info['revisor_nombre'] ?? '');
    $revisorCorreo = trim($info['revisor_correo'] ?? '');

    // =====================================================
    // 3) VALIDAR CUESTIONARIO
    //    SOLO CAMBIAMOS:
    //    - estatus_validacion
    //    - validado_por
    //    - validado_en
    // =====================================================
    $sqlUpdate = "
        UPDATE cuestionarios
        SET
            estatus_validacion = 'validado',
            validado_por = ?,
            validado_en = NOW()
        WHERE id = ?
          AND eliminado = 0
        LIMIT 1
    ";

    $stmtUpdate = $mysqli->prepare($sqlUpdate);
    if (!$stmtUpdate) {
        throw new Exception("Error prepare update: " . $mysqli->error);
    }

    $stmtUpdate->bind_param("si", $validadorNombre, $id);

    if (!$stmtUpdate->execute()) {
        throw new Exception("Error al validar el cuestionario: " . $stmtUpdate->error);
    }

    $stmtUpdate->close();

    // =====================================================
    // 4) ENVIAR CORREO AL REVISOR
    // =====================================================
    $email_ok = false;
    $email_msg = "No se envió correo (el cuestionario no tiene revisor asignado o el revisor no tiene correo).";

    if ($revisorNombre !== '' && $revisorCorreo !== '') {
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
            $mail->addAddress($revisorCorreo, $revisorNombre);

            $folioSafe = htmlspecialchars($folio, ENT_QUOTES, 'UTF-8');
            $revisorSafe = htmlspecialchars($revisorNombre, ENT_QUOTES, 'UTF-8');
            $validadorSafe = htmlspecialchars($validadorNombre, ENT_QUOTES, 'UTF-8');
            $fechaSafe = htmlspecialchars(date("Y-m-d H:i:s"), ENT_QUOTES, 'UTF-8');

            $mail->isHTML(true);
            $mail->Subject = "SGC: El cuestionario {$folioSafe} ya fue validado";

            $mail->Body = "
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
                                  <h1 style='margin:0; font-size:20px; color:#ffffff;'>SGC - Cuestionario validado</h1>
                                </td>
                              </tr>
                            </table>
                          </td>
                        </tr>

                        <tr>
                          <td style='padding:20px; color:#30305c;'>
                            <p style='margin:0 0 10px 0;'>
                              Hola <strong>{$revisorSafe}</strong>,
                            </p>

                            <p style='margin:0 0 10px 0;'>
                              Te informamos que el cuestionario
                              <strong>{$folioSafe}</strong>
                              ya fue validado correctamente.
                            </p>

                            <p style='margin:0 0 8px 0;'><strong>Cuestionario:</strong> {$folioSafe}</p>
                            <p style='margin:0 0 8px 0;'><strong>Validador:</strong> {$validadorSafe}</p>
                            <p style='margin:0 0 8px 0;'><strong>Fecha de validación:</strong> {$fechaSafe}</p>

                            <div style='background:#f6f7fb; border:1px solid #e5e7eb; border-radius:10px; padding:12px 14px; margin-top:12px;'>
                              <div style='font-weight:700; margin-bottom:6px;'>Estatus:</div>
                              <div style='color:#111827;'>El cuestionario ha quedado validado en el sistema.</div>
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

            $mail->send();
            $email_ok = true;
            $email_msg = "Correo enviado al revisor: {$revisorCorreo}";
        } catch (Throwable $mailErr) {
            $email_ok = false;
            $email_msg = "Fallo correo: " . $mailErr->getMessage();
            error_log("SGC mail validar_cuestionario error: " . $mailErr->getMessage());
        }
    }

    echo json_encode([
        "ok" => true,
        "msg" => "Cuestionario validado correctamente.",
        "email_ok" => $email_ok,
        "email_msg" => $email_msg,
        "notificado_a" => $revisorCorreo,
        "revisor" => $revisorNombre,
        "folio" => $folio
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "ok"      => false,
        "msg"     => "Error en servidor",
        "detalle" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}