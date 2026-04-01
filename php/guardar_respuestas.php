<?php
header("Content-Type: application/json; charset=utf-8");
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION["nombre"])) {
  http_response_code(401);
  echo json_encode(["ok" => false, "msg" => "No autorizado"], JSON_UNESCAPED_UNICODE);
  exit;
}

require_once __DIR__ . "/conexion.php";

// =========================
// PHPMailer (manual, sin composer)
// =========================
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . "/PHPMailer/src/PHPMailer.php";
require_once __DIR__ . "/PHPMailer/src/SMTP.php";
require_once __DIR__ . "/PHPMailer/src/Exception.php";

try {
  $mysqli = db();

  $usuarioActual = trim($_SESSION["nombre"]);

  $cid     = intval($_POST["cid"] ?? 0);
  $folio   = trim($_POST["folio"] ?? "");
  $seccion = trim($_POST["seccion"] ?? "");
  $enviarCorreo = intval($_POST["enviar_correo"] ?? 1);

  $preguntas   = $_POST["pregunta"] ?? [];
  $respuestas  = $_POST["respuesta"] ?? [];
  $comentarios = $_POST["comentarios"] ?? [];

  // Si no viene cid, resolver por folio
  if ($cid <= 0) {
    if ($folio === "") {
      throw new Exception("Falta cid o folio.");
    }

    $stmtF = $mysqli->prepare("SELECT id, folio FROM cuestionarios WHERE folio = ? LIMIT 1");
    if (!$stmtF) {
      throw new Exception($mysqli->error);
    }

    $stmtF->bind_param("s", $folio);
    $stmtF->execute();
    $row = $stmtF->get_result()->fetch_assoc();
    $stmtF->close();

    if (!$row) {
      throw new Exception("No existe cuestionario con ese folio.");
    }

    $cid = intval($row["id"]);
    $folio = $row["folio"] ?? $folio;
  }

  if ($seccion === "" || !is_array($preguntas) || count($preguntas) === 0) {
    throw new Exception("Datos incompletos (seccion/preguntas).");
  }

  $sql = "INSERT INTO cuestionario_respuestas
          (cuestionario_id, seccion, pregunta, respuesta, comentarios, nom_evidencia)
          VALUES (?, ?, ?, ?, ?, ?)
          ON DUPLICATE KEY UPDATE
            respuesta = VALUES(respuesta),
            comentarios = VALUES(comentarios),
            nom_evidencia = VALUES(nom_evidencia)";

  $stmt = $mysqli->prepare($sql);
  if (!$stmt) {
    throw new Exception("Prepare failed: " . $mysqli->error);
  }

  // Base uploads
  $uploadsBaseAbs = __DIR__ . "/../uploads";
  $uploadsBaseRel = "uploads";
  if (!is_dir($uploadsBaseAbs)) {
    mkdir($uploadsBaseAbs, 0777, true);
  }

  $guardadas = 0;
  $archivosGuardados = 0;
  $preguntasBloqueadas = [];

  for ($i = 0; $i < count($preguntas); $i++) {
    $p = (string)$preguntas[$i];
    $r = (string)($respuestas[$i] ?? "");
    $c = (string)($comentarios[$i] ?? "");

    // =========================
    // NO modificar si ya está validada
    // =========================
    $stmtVal = $mysqli->prepare("
      SELECT validada, nom_evidencia
      FROM cuestionario_respuestas
      WHERE cuestionario_id = ? AND seccion = ? AND pregunta = ?
      LIMIT 1
    ");
    if (!$stmtVal) {
      throw new Exception("Prepare validada failed: " . $mysqli->error);
    }

    $stmtVal->bind_param("iss", $cid, $seccion, $p);
    $stmtVal->execute();
    $rowVal = $stmtVal->get_result()->fetch_assoc();
    $stmtVal->close();

    $isValidada = $rowVal && intval($rowVal["validada"] ?? 0) === 1;
    if ($isValidada) {
      $preguntasBloqueadas[] = $p;
      continue;
    }

    $key = "evidencia_" . $p;

    $dirAbs = $uploadsBaseAbs . "/cid_" . $cid . "/" . $seccion . "/pregunta_" . preg_replace('/\D/', '', $p);
    $dirRel = $uploadsBaseRel . "/cid_" . $cid . "/" . $seccion . "/pregunta_" . preg_replace('/\D/', '', $p);
    if (!is_dir($dirAbs)) {
      mkdir($dirAbs, 0777, true);
    }

    // Evidencias previas
    $prevEvidencias = [];
    if ($rowVal && !empty($rowVal["nom_evidencia"])) {
      $tmp = json_decode($rowVal["nom_evidencia"], true);
      if (is_array($tmp)) {
        $prevEvidencias = $tmp;
      }
    }

    $evidencias = $prevEvidencias;

    // Subir nuevas evidencias
    if (isset($_FILES[$key]) && is_array($_FILES[$key]["name"])) {
      $n = count($_FILES[$key]["name"]);

      for ($f = 0; $f < $n; $f++) {
        if ($_FILES[$key]["error"][$f] !== UPLOAD_ERR_OK) {
          continue;
        }

        $orig = basename($_FILES[$key]["name"][$f]);
        $origSafe = preg_replace('/[^a-zA-Z0-9_\.\-]/', '_', $orig);
        $final = time() . "_" . $origSafe;

        $destAbs = $dirAbs . "/" . $final;
        $destRel = $dirRel . "/" . $final;

        if (move_uploaded_file($_FILES[$key]["tmp_name"][$f], $destAbs)) {
          $evidencias[] = [
            "name" => $orig,
            "url"  => $destRel
          ];
          $archivosGuardados++;
        }
      }
    }

    $nom_evidencia = json_encode($evidencias, JSON_UNESCAPED_UNICODE);

    $stmt->bind_param("isssss", $cid, $seccion, $p, $r, $c, $nom_evidencia);
    if (!$stmt->execute()) {
      throw new Exception("Execute failed: " . $stmt->error);
    }

    $guardadas++;
  }

  $stmt->close();

  // Actualizar último modificador del cuestionario
  $upd = $mysqli->prepare("
    UPDATE cuestionarios
    SET actualizado_por = ?, actualizado_en = NOW()
    WHERE id = ?
  ");
  if (!$upd) {
    throw new Exception("Prepare update failed: " . $mysqli->error);
  }

  $upd->bind_param("si", $usuarioActual, $cid);
  $upd->execute();
  $upd->close();

  // =====================================================
  // CORREO GENERAL (solo para guardado normal de sección)
  // =====================================================
  $email_ok = false;
  $email_msg = ($enviarCorreo === 1)
    ? "No se envió correo."
    : "Correo omitido por flujo de corrección de nota.";

  if ($enviarCorreo === 1) {
    try {
      // ✅ DESTINATARIO MANUAL
      $DEST_EMAIL  = "amolina@sclconsultores.com.mx";
      $DEST_NOMBRE = "Revisor";

      if (!$DEST_EMAIL) {
        throw new Exception("No configuraste el correo manual del destinatario.");
      }

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
      $mail->addAddress($DEST_EMAIL, $DEST_NOMBRE);

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
      $seccionSafe = htmlspecialchars($seccionRomana, ENT_QUOTES, "UTF-8");
      $usuarioSafe = htmlspecialchars($usuarioActual, ENT_QUOTES, "UTF-8");
      $folioSafe = htmlspecialchars($folio, ENT_QUOTES, "UTF-8");

      $mail->isHTML(true);
      $mail->Subject = "SGC: Actualización de la sección {$seccionRomana} ({$folioSafe})";

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
                            <h1 style='margin:0; font-size:20px; color:#ffffff;'>SGC - Avance en el cuestionario</h1>
                          </td>
                        </tr>
                      </table>
                    </td>
                  </tr>

                  <tr>
                    <td style='padding:20px; color:#30305c;'>
                      <h2 style='margin:0 0 10px 0;'>
                        Cuestionario: {$folioSafe}
                      </h2>
                      <p style='margin:0 0 8px 0;'>
                        {$usuarioSafe} ha guardado un avance en el cuestionario de cumplimiento en la <strong>sección {$seccionSafe}</strong>.
                        La información ya está disponible para su consulta o revisión parcial.
                      </p>

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
      $email_ok = true;
      $email_msg = "Correo enviado a: {$DEST_EMAIL}";
    } catch (Throwable $mailErr) {
      $email_ok = false;
      $email_msg = "Fallo correo: " . $mailErr->getMessage();
      error_log("SGC mail guardar_respuestas error: " . $mailErr->getMessage());
    }
  }

  $msg = "Guardado: {$guardadas} preguntas. Archivos: {$archivosGuardados}";
  if (!empty($preguntasBloqueadas)) {
    $msg .= ". Preguntas bloqueadas por validación: " . implode(", ", $preguntasBloqueadas);
  }

  echo json_encode([
    "ok" => true,
    "msg" => $msg,
    "cid" => $cid,
    "folio" => $folio,
    "seccion" => $seccion,
    "actualizado_por" => $usuarioActual,
    "email_ok" => $email_ok,
    "email_msg" => $email_msg,
    "preguntas_bloqueadas" => $preguntasBloqueadas
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    "ok" => false,
    "msg" => "Error en servidor",
    "detalle" => $e->getMessage()
  ], JSON_UNESCAPED_UNICODE);
}