<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

class NotificationMailer
{
  private static array $debugLog = [];

  public static function configPath(): string
  {
    return dirname(__DIR__) . '/config/mail.php';
  }

  public static function loadConfig(): array
  {
    $path = self::configPath();
    if (!is_file($path)) {
      throw new RuntimeException('No existe el archivo de configuracion SMTP en app/config/mail.php');
    }

    $cfg = require $path;
    if (!is_array($cfg)) {
      throw new RuntimeException('La configuracion SMTP de app/config/mail.php no es valida.');
    }

    return $cfg;
  }

  public static function adminRecipients(): array
  {
    $cfg = self::loadConfig();
    return array_values(array_filter(array_map('trim', $cfg['admin_recipients'] ?? [])));
  }

  public static function send(array $to, string $subject, string $htmlBody, string $textBody = ''): void
  {
    self::$debugLog = [];

    $cfg = self::loadConfig();
    if (empty($cfg['enabled'])) {
      throw new RuntimeException('El envio SMTP esta deshabilitado en app/config/mail.php');
    }

    $host = (string)($cfg['host'] ?? '');
    $port = (int)($cfg['port'] ?? 0);
    $encryption = strtolower((string)($cfg['encryption'] ?? 'tls'));
    $username = (string)($cfg['username'] ?? '');
    $password = (string)($cfg['password'] ?? '');
    $fromEmail = (string)($cfg['from_email'] ?? '');
    $fromName = (string)($cfg['from_name'] ?? APP_NAME);
    $timeout = (int)($cfg['connect_timeout'] ?? 20);
    $verifyPeer = (bool)($cfg['verify_peer'] ?? true);
    $verifyPeerName = (bool)($cfg['verify_peer_name'] ?? true);
    $allowSelfSigned = (bool)($cfg['allow_self_signed'] ?? false);
    $debugEnabled = (bool)($cfg['debug'] ?? false);

    if ($host === '' || $port <= 0 || $username === '' || $password === '' || $fromEmail === '') {
      throw new RuntimeException('La configuracion SMTP esta incompleta.');
    }

    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
      throw new RuntimeException('El correo remitente no es valido: ' . $fromEmail);
    }

    $recipients = array_values(array_filter(array_map('trim', $to)));
    if (empty($recipients)) {
      throw new RuntimeException('No hay destinatarios configurados para el correo.');
    }

    foreach ($recipients as $recipient) {
      if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Destinatario no valido en app/config/mail.php: ' . $recipient);
      }
    }

    $mail = new PHPMailer(true);

    try {
      $mail->isSMTP();
      $mail->Host = $host;
      $mail->Port = $port;
      $mail->SMTPAuth = true;
      $mail->Username = $username;
      $mail->Password = $password;
      $mail->Timeout = $timeout;
      $mail->CharSet = 'UTF-8';
      $mail->Encoding = 'base64';
      $mail->setFrom($fromEmail, $fromName);

      if ($encryption === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
      } elseif ($encryption === 'tls') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
      } else {
        $mail->SMTPSecure = false;
        $mail->SMTPAutoTLS = false;
      }

      $mail->SMTPOptions = [
        'ssl' => [
          'verify_peer' => $verifyPeer,
          'verify_peer_name' => $verifyPeerName,
          'allow_self_signed' => $allowSelfSigned,
        ],
      ];

      if ($debugEnabled) {
        $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        $mail->Debugoutput = static function ($str, $level): void {
          NotificationMailer::debug('L' . $level . ' ' . trim($str));
        };
      }

      foreach ($recipients as $recipient) {
        $mail->addAddress($recipient);
      }

      $mail->isHTML(true);
      $mail->Subject = $subject;
      $mail->Body = $htmlBody;
      $mail->AltBody = $textBody !== '' ? $textBody : strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], ["\n", "\n", "\n", "\n\n"], $htmlBody));

      $mail->send();
    } catch (PHPMailerException $e) {
      throw new RuntimeException('Error SMTP con PHPMailer: ' . $e->getMessage() . '. ' . self::debugSummary());
    } catch (Throwable $e) {
      throw new RuntimeException('Fallo inesperado al enviar correo: ' . $e->getMessage() . '. ' . self::debugSummary());
    }
  }

  public static function debugSummary(): string
  {
    return empty(self::$debugLog) ? 'Trace: sin salida SMTP' : 'Trace: ' . implode(' | ', self::$debugLog);
  }

  private static function debug(string $line): void
  {
    self::$debugLog[] = $line;
    if (count(self::$debugLog) > 60) {
      array_shift(self::$debugLog);
    }
  }
}
