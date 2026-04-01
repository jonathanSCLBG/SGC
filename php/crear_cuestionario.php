<?php
header("Content-Type: application/json; charset=utf-8");
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION["nombre"])) {
  http_response_code(401);
  echo json_encode(["ok" => false, "msg" => "No autorizado"]);
  exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  http_response_code(405);
  echo json_encode(["ok" => false, "msg" => "Método no permitido"]);
  exit;
}

require_once __DIR__ . "/conexion.php";

// =========================
// PHPMailer
// =========================
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . "/PHPMailer/src/PHPMailer.php";
require_once __DIR__ . "/PHPMailer/src/SMTP.php";
require_once __DIR__ . "/PHPMailer/src/Exception.php";

try {
  $mysqli = db();

  $creadoPor = trim($_SESSION["nombre"]);
  $fechaVencimiento = date("Y-m-d H:i:s", strtotime("+30 days"));
  $revisorId = isset($_POST["revisor_id"]) ? (int)$_POST["revisor_id"] : 0;

  if ($revisorId <= 0) {
    throw new Exception("Debes seleccionar un revisor.");
  }

  // =====================================================
  // VALIDAR REVISOR
  // =====================================================
  // AJUSTA "email" si tu columna tiene otro nombre
  $stmtRev = $mysqli->prepare("
    SELECT id, Nombre, Usuario, correo, tipo_usuario
    FROM usuarios
    WHERE id = ?
    LIMIT 1
  ");
  if (!$stmtRev) {
    throw new Exception("Error prepare revisor: " . $mysqli->error);
  }

  $stmtRev->bind_param("i", $revisorId);
  $stmtRev->execute();
  $resRev = $stmtRev->get_result();
  $revisor = $resRev->fetch_assoc();
  $stmtRev->close();

  if (!$revisor) {
    throw new Exception("El revisor seleccionado no existe.");
  }

  if (strtolower(trim($revisor["tipo_usuario"] ?? "")) !== "revisor") {
    throw new Exception("El usuario seleccionado no es un revisor válido.");
  }

  $revisorNombre = trim($revisor["Nombre"] ?? "");
  $revisorEmail  = trim($revisor["correo"] ?? "");

  // =====================================================
  // GENERAR FOLIO E INSERTAR
  // =====================================================
  $folio = null;
  $cid = null;

  for ($i = 0; $i < 10; $i++) {
    $folioTmp = "SCL-" . random_int(100000, 999999);

    $stmt = $mysqli->prepare("
      INSERT INTO cuestionarios (folio, fecha_vencimiento, creado_por, revisor_id)
      VALUES (?, ?, ?, ?)
    ");
    if (!$stmt) {
      throw new Exception("Error prepare insert: " . $mysqli->error);
    }

    $stmt->bind_param("sssi", $folioTmp, $fechaVencimiento, $creadoPor, $revisorId);

    if ($stmt->execute()) {
      $folio = $folioTmp;
      $cid = $mysqli->insert_id;
      $stmt->close();
      break;
    }

    $stmt->close();

    if ($mysqli->errno != 1062) {
      throw new Exception("Error al insertar: " . $mysqli->error);
    }
  }

  if (!$folio || !$cid) {
    throw new Exception("No se pudo generar un folio único.");
  }

  // =====================================================
  // ENVIAR CORREO AL REVISOR SELECCIONADO
  // =====================================================
  $email_ok  = false;
  $email_msg = "No se intentó enviar correo.";

  try {
    if (!empty($revisorEmail)) {
      $link = "http://localhost/Interfaz_final/login.html";

      $mail = new PHPMailer(true);
      $mail->CharSet = "UTF-8";

      $mail->isSMTP();
      $mail->Host       = "smtp.office365.com";
      $mail->SMTPAuth   = true;
      $mail->Username   = "sgc_scl@sclconsultores.com.mx";
      $mail->Password   = "L(769676559030uh";
      $mail->SMTPSecure = "STARTTLS";
      $mail->Port       = 587;

      $mail->setFrom("sgc_scl@sclconsultores.com.mx", "SGC - Notificaciones");
      $mail->addAddress($revisorEmail, $revisorNombre);

      $mail->isHTML(true);
      $mail->Subject = "SGC: Nuevo cuestionario asignado ($folio)";
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
                              <h1 style='margin:0; font-size:20px; color:#ffffff;'>SGC - NUEVO CUESTIONARIO ASIGNADO</h1>
                            </td>
                          </tr>
                        </table>
                      </td>
                    </tr>

                    <tr>
                      <td style='padding:20px; color:#30305c;'>
                        <h2 style='margin:0 0 10px 0;'>Hola, {$revisorNombre}</h2>

                        <p>Se te ha asignado un nuevo cuestionario de cumplimiento en la plataforma.</p>

                        <p style='margin:0 0 8px 0;'><strong>Folio:</strong> {$folio}</p>
                        <p style='margin:0 0 8px 0;'><strong>Creado por:</strong> {$creadoPor}</p>

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
      $email_ok  = true;
      $email_msg = "Correo enviado al revisor seleccionado.";
    } else {
      $email_ok  = false;
      $email_msg = "El revisor no tiene correo registrado.";
    }

  } catch (Throwable $mailErr) {
    $email_ok  = false;
    $email_msg = "Fallo correo: " . $mailErr->getMessage();
    error_log("SGC mail error: " . $mailErr->getMessage());
  }

  echo json_encode([
    "ok" => true,
    "cid" => $cid,
    "folio" => $folio,
    "revisor_id" => $revisorId,
    "revisor_nombre" => $revisorNombre,
    "email_ok" => $email_ok,
    "email_msg" => $email_msg
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    "ok" => false,
    "msg" => "Error en servidor",
    "detalle" => $e->getMessage()
  ], JSON_UNESCAPED_UNICODE);
}