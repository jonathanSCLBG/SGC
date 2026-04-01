<?php
header("Content-Type: application/json; charset=utf-8");
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . "/conexion.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . "/PHPMailer/src/PHPMailer.php";
require_once __DIR__ . "/PHPMailer/src/SMTP.php";
require_once __DIR__ . "/PHPMailer/src/Exception.php";

try {

    if (!isset($_SESSION["nombre"])) {
        throw new Exception("Usuario no autenticado");
    }

    $usuarioActual = trim($_SESSION["nombre"]);
    $id = isset($_POST["id"]) ? (int)$_POST["id"] : 0;

    if ($id <= 0) {
        throw new Exception("ID de nota inválido");
    }

    $mysqli = db();

    /* ============================
       Obtener datos de la nota
    ============================ */
    $stmt = $mysqli->prepare("
        SELECT
            n.id,
            n.cuestionario_id,
            n.seccion,
            n.pregunta,
            n.nota,
            q.folio
        FROM notas_revision n
        LEFT JOIN cuestionarios q ON q.id = n.cuestionario_id
        WHERE n.id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception("Error al preparar SELECT: " . $mysqli->error);
    }

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $nota = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$nota) {
        throw new Exception("No se encontró la nota");
    }

    /* ============================
       Guardar quién corrigió
    ============================ */
    $upd = $mysqli->prepare("
        UPDATE notas_revision
        SET corregida_por = ?, corregida_en = NOW()
        WHERE id = ?
    ");

    if (!$upd) {
        throw new Exception("Error al preparar UPDATE: " . $mysqli->error);
    }

    $upd->bind_param("si", $usuarioActual, $id);

    if (!$upd->execute()) {
        throw new Exception("Error al registrar corrección: " . $upd->error);
    }

    $upd->close();

    /* ============================
       CORREO MANUAL
    ============================ */
    $REVISOR_EMAIL  = "amolina@sclconsultores.com.mx";
    $REVISOR_NOMBRE = "Hector Suverza";

    $cid      = $nota["cuestionario_id"];
    $folio    = $nota["folio"] ?? ("CID " . $cid);
    $seccion  = $nota["seccion"];
    $pregunta = $nota["pregunta"];
    $notaTxt  = $nota["nota"];

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

    $folioSafe    = htmlspecialchars($folio, ENT_QUOTES, "UTF-8");
    $seccionSafe  = htmlspecialchars($seccionRomana, ENT_QUOTES, "UTF-8");
    $preguntaSafe = htmlspecialchars((string)$pregunta, ENT_QUOTES, "UTF-8");
    $notaTxtSafe  = nl2br(htmlspecialchars((string)$notaTxt, ENT_QUOTES, "UTF-8"));
    $usuarioSafe  = htmlspecialchars($usuarioActual, ENT_QUOTES, "UTF-8");

    $link = "http://localhost/Interfaz_final/app/revisor/index.html?cid=" . urlencode((string)$cid) . "&folio=" . urlencode((string)$folio);

    $mail = new PHPMailer(true);
    $mail->CharSet = "UTF-8";

    $SMTP_USER = "sgc_scl@sclconsultores.com.mx";
    $SMTP_PASS = "L(769676559030uh";

    $mail->isSMTP();
    $mail->Host       = "smtp.office365.com";
    $mail->SMTPAuth   = true;
    $mail->Username   = $SMTP_USER;
    $mail->Password   = $SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom($SMTP_USER, "SGC - Notificaciones");
    $mail->addAddress($REVISOR_EMAIL, $REVISOR_NOMBRE);

    $mail->isHTML(true);
    $mail->Subject = "SGC: Nota resuelta en {$folioSafe} (Sección {$seccionSafe} · Pregunta {$preguntaSafe})";

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
                          <h1 style='margin:0; font-size:20px; color:#ffffff;'>SGC - Actualización de nota</h1>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>

                <tr>
                  <td style='padding:20px; color:#30305c;'>
                    <p style='margin:0 0 8px 0;'>
                      Una nota previamente emitida ha sido atendida por el preparador <strong>{$usuarioSafe}</strong> y está lista para su revalidación.
                    </p>

                    <p style='margin:0 0 8px 0;'><strong>Cuestionario:</strong> {$folioSafe}</p>
                    <p style='margin:0 0 8px 0;'><strong>Sección:</strong> {$seccionSafe}</p>
                    <p style='margin:0 0 8px 0;'><strong>Pregunta:</strong> {$preguntaSafe}</p>

                    <div style='background:#f6f7fb; border:1px solid #e5e7eb; border-radius:10px; padding:12px 14px;'>
                      <div style='font-weight:700; margin-bottom:6px;'>Nota de referencia:</div>
                      <div style='color:#111827;'>{$notaTxtSafe}</div>
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
    </html>";

    $mail->send();

    echo json_encode([
        "ok" => true,
        "msg" => "Corrección registrada y correo enviado"
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        "ok" => false,
        "msg" => "Error en servidor",
        "detalle" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}