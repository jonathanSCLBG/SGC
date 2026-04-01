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

    if ($cid <= 0 || $seccion === '' || $pregunta === '' || $nota === '') {
        throw new Exception("Datos incompletos.");
    }

    $mysqli = db();

    // =====================================================
    // 1) Guardar nota (tu tabla real: notas_revision)
    // =====================================================
    $sql = "
        INSERT INTO notas_revision
        (cuestionario_id, seccion, pregunta, nota, revisor_nombre)
        VALUES (?, ?, ?, ?, ?)
    ";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $mysqli->error);
    }

    $stmt->bind_param("issss", $cid, $seccion, $pregunta, $nota, $revisor_nombre);

    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    $stmt->close();

    // =====================================================
    // 2) Resolver "último que modificó" (desde cuestionarios)
    // =====================================================
    $folio = "CID-" . $cid;
    $ultimoNombre = "";
    $ultimoEn = "";

    $q = $mysqli->prepare("
        SELECT folio, actualizado_por, actualizado_en
        FROM cuestionarios
        WHERE id = ?
        LIMIT 1
    ");
    if (!$q) throw new Exception("Prepare cuestionarios failed: " . $mysqli->error);

    $q->bind_param("i", $cid);
    $q->execute();
    $qc = $q->get_result()->fetch_assoc();
    $q->close();

    if ($qc) {
        $folio = $qc["folio"] ?? $folio;
        $ultimoNombre = trim($qc["actualizado_por"] ?? "");
        $ultimoEn = $qc["actualizado_en"] ?? "";
    }

    // =====================================================
    // 3) Buscar correo del último modificador (tabla usuarios)
    //    Tu tabla: usuarios(Nombre, correo)
    // =====================================================
    $destEmail = "";
    $destNombre = $ultimoNombre !== "" ? $ultimoNombre : "Preparador";

    if ($ultimoNombre !== "") {
        $u = $mysqli->prepare("
            SELECT correo
            FROM usuarios
            WHERE Nombre = ?
            LIMIT 1
        ");
        if ($u) {
            $u->bind_param("s", $ultimoNombre);
            $u->execute();
            $ru = $u->get_result()->fetch_assoc();
            $u->close();

            $destEmail = trim($ru["correo"] ?? "");
        }
    }

    // =====================================================
    // 4) Enviar correo (NO bloquea si falla)
    // =====================================================
    $email_ok = false;
    $email_msg = "No se envió correo (no se encontró email del último modificador).";

    if ($destEmail !== "") {
        try {
            // 🔧 Gmail (pruebas)
            $gmailUser    = "sgc_scl@sclconsultores.com.mx";
            $gmailAppPass = "L(769676559030uh"; // SIN espacios

            // Link al SGC (puedes mandar directo a la sección)
            $link = "http://localhost/Interfaz_final/login.html";

            $mail = new PHPMailer(true);
            $mail->CharSet = "UTF-8";

            $mail->isSMTP();
            $mail->Host       = "smtp.office365.com";
            $mail->SMTPAuth   = true;
            $mail->Username   = $gmailUser;
            $mail->Password   = $gmailAppPass;
            $mail->SMTPSecure = "STARTTLS";
            $mail->Port       = 587;

            $mail->setFrom($gmailUser, "SGC - Notificaciones");
            $mail->addAddress($destEmail, $destNombre);

            $seccionSafe  = htmlspecialchars($seccionRomana, ENT_QUOTES, "UTF-8");


            $mail->isHTML(true);
            $mail->Subject = "SGC: Tienes una nota en la sección {$seccionSafe} · Pregunta {$pregunta} ({$folio})";

            // Email con diseño parecido al tuyo
            $notaSafe = nl2br(htmlspecialchars($nota));
            $pregSafe = htmlspecialchars($pregunta);
            $revisorSafe = htmlspecialchars($revisor_nombre);
            $fechaSafe = htmlspecialchars(date("Y-m-d H:i:s"));

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
                                  <h1 style='margin:0; font-size:20px; color:#ffffff;'>SGC - Se ha generado una nueva nota</h1>
                                </td>
                              </tr>
                            </table>
                          </td>
                        </tr>

                        <tr>
                          <td style='padding:20px; color:#30305c;'>
                            <p style='margin:0 0 10px 0;'>
                                El revisor ha registrado una nota que requiere su atención para continuar con el proceso de cumplimiento.
                            </p>

                            <p style='margin:0 0 8px 0;'><strong>Cuestionario:</strong> {$folio}</p>
                            <p style='margin:0 0 8px 0;'><strong>Sección:</strong> {$seccionSafe}</p>
                            <p style='margin:0 0 8px 0;'><strong>Pregunta:</strong> {$pregSafe}</p>
                            <p style='margin:0 0 8px 0;'><strong>Revisor:</strong> {$revisorSafe}</p>

                            <div style='background:#f6f7fb; border:1px solid #e5e7eb; border-radius:10px; padding:12px 14px;'>
                              <div style='font-weight:700; margin-bottom:6px;'>Nota:</div>
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

            $mail->send();
            $email_ok = true;
            $email_msg = "Correo enviado a: {$destEmail}";
        } catch (Throwable $mailErr) {
            $email_ok = false;
            $email_msg = "Fallo correo: " . $mailErr->getMessage();
            error_log("SGC mail guardar_nota error: " . $mailErr->getMessage());
        }
    }

    echo json_encode([
        "ok"  => true,
        "msg" => "Nota guardada correctamente.",
        "email_ok" => $email_ok,
        "email_msg" => $email_msg,
        "notificado_a" => $destEmail,
        "ultimo_modificador" => $ultimoNombre
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "ok"      => false,
        "msg"     => "Error en servidor",
        "detalle" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}