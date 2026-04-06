<?php

class NotificationMailer
{
  public static function send(array $to, string $subject, string $htmlBody, string $textBody = ''): void
  {
    $cfg = require __DIR__ . '/../config/mail.php';
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

    if ($host === '' || $port <= 0 || $username === '' || $password === '' || $fromEmail === '') {
      throw new RuntimeException('La configuracion SMTP esta incompleta.');
    }

    $recipients = array_values(array_filter(array_map('trim', $to)));
    if (empty($recipients)) {
      throw new RuntimeException('No hay destinatarios configurados para el correo.');
    }

    $transportHost = $encryption === 'ssl' ? 'ssl://' . $host : $host;
    $socket = @stream_socket_client($transportHost . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
    if (!$socket) {
      throw new RuntimeException('No se pudo conectar al servidor SMTP: ' . $errstr . ' (' . $errno . ')');
    }

    stream_set_timeout($socket, $timeout);

    try {
      self::expect($socket, [220]);
      self::command($socket, 'EHLO localhost', [250]);

      if ($encryption === 'tls') {
        self::command($socket, 'STARTTLS', [220]);
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
          throw new RuntimeException('No se pudo iniciar TLS con el servidor SMTP.');
        }
        self::command($socket, 'EHLO localhost', [250]);
      }

      self::command($socket, 'AUTH LOGIN', [334]);
      self::command($socket, base64_encode($username), [334]);
      self::command($socket, base64_encode($password), [235]);

      self::command($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
      foreach ($recipients as $recipient) {
        self::command($socket, 'RCPT TO:<' . $recipient . '>', [250, 251]);
      }

      self::command($socket, 'DATA', [354]);

      $boundary = 'b' . bin2hex(random_bytes(12));
      $headers = [
        'From: ' . self::headerAddress($fromEmail, $fromName),
        'To: ' . implode(', ', $recipients),
        'Subject: ' . self::encodeHeader($subject),
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
      ];

      $textBody = $textBody !== '' ? $textBody : strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], ["\n", "\n", "\n", "\n\n"], $htmlBody));

      $message = implode("\r\n", $headers) . "\r\n\r\n";
      $message .= '--' . $boundary . "\r\n";
      $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
      $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
      $message .= $textBody . "\r\n\r\n";
      $message .= '--' . $boundary . "\r\n";
      $message .= "Content-Type: text/html; charset=UTF-8\r\n";
      $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
      $message .= $htmlBody . "\r\n\r\n";
      $message .= '--' . $boundary . "--\r\n.";

      fwrite($socket, $message . "\r\n");
      self::expect($socket, [250]);
      self::command($socket, 'QUIT', [221]);
    } finally {
      fclose($socket);
    }
  }

  private static function command($socket, string $command, array $expectedCodes): void
  {
    fwrite($socket, $command . "\r\n");
    self::expect($socket, $expectedCodes);
  }

  private static function expect($socket, array $expectedCodes): void
  {
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
      $response .= $line;
      if (strlen($line) >= 4 && $line[3] === ' ') {
        break;
      }
    }

    if ($response === '') {
      throw new RuntimeException('El servidor SMTP no respondio.');
    }

    $code = (int)substr($response, 0, 3);
    if (!in_array($code, $expectedCodes, true)) {
      throw new RuntimeException('Respuesta SMTP inesperada: ' . trim($response));
    }
  }

  private static function headerAddress(string $email, string $name): string
  {
    return self::encodeHeader($name) . ' <' . $email . '>';
  }

  private static function encodeHeader(string $value): string
  {
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
  }
}
