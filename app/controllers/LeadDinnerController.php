<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Csrf.php';
require_once __DIR__ . '/../models/LeadDinnerEntry.php';
require_once __DIR__ . '/../models/LeadDinnerStatus.php';

class LeadDinnerController extends Controller
{
  private function uploadErrorMessage(int $code): string
  {
    return match ($code) {
      UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El voucher supera el tamano maximo permitido por el servidor.',
      UPLOAD_ERR_PARTIAL => 'El voucher no termino de subirse. Intenta nuevamente.',
      UPLOAD_ERR_NO_FILE => 'Debes adjuntar el voucher de consumo.',
      UPLOAD_ERR_NO_TMP_DIR => 'El servidor no tiene carpeta temporal para procesar el voucher.',
      UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir el voucher en el servidor.',
      UPLOAD_ERR_EXTENSION => 'La subida del voucher fue bloqueada por una extension del servidor.',
      default => 'No se pudo procesar el voucher adjunto.',
    };
  }

  private function uploadVoucher(): array
  {
    $file = $_FILES['voucher'] ?? null;
    if (!$file || !is_array($file)) {
      throw new RuntimeException('Debes adjuntar el voucher de consumo.');
    }

    $uploadError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError !== UPLOAD_ERR_OK) {
      throw new RuntimeException($this->uploadErrorMessage($uploadError));
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
      throw new RuntimeException('El voucher subido no es valido.');
    }

    $originalName = (string)($file['name'] ?? 'voucher');
    $ext = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
    $mime = strtolower((string)($file['type'] ?? ''));
    if (function_exists('finfo_open')) {
      $finfo = finfo_open(FILEINFO_MIME_TYPE);
      if ($finfo !== false) {
        $detectedMime = finfo_file($finfo, $tmp);
        finfo_close($finfo);
        if (is_string($detectedMime) && $detectedMime !== '') {
          $mime = strtolower($detectedMime);
        }
      }
    }

    $allowedByExtension = [
      'jpg' => 'jpg',
      'jpeg' => 'jpg',
      'png' => 'png',
      'webp' => 'webp',
      'pdf' => 'pdf',
      'heic' => 'heic',
      'heif' => 'heif',
    ];
    $allowedByMime = [
      'image/jpeg' => 'jpg',
      'image/jpg' => 'jpg',
      'image/png' => 'png',
      'image/webp' => 'webp',
      'application/pdf' => 'pdf',
      'image/heic' => 'heic',
      'image/heif' => 'heif',
      'image/heic-sequence' => 'heic',
      'image/heif-sequence' => 'heif',
    ];

    $normalizedExt = $allowedByExtension[$ext] ?? ($allowedByMime[$mime] ?? '');
    if ($normalizedExt === '') {
      throw new RuntimeException('El voucher debe ser JPG, PNG, WEBP, HEIC, HEIF o PDF.');
    }

    $dir = dirname(__DIR__, 2) . '/uploads/leads-cena';
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
      throw new RuntimeException('No se pudo crear la carpeta para vouchers.');
    }

    $fileName = 'voucher_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $normalizedExt;
    $target = $dir . '/' . $fileName;

    if (!move_uploaded_file($tmp, $target)) {
      throw new RuntimeException('No se pudo guardar el voucher.');
    }

    return [
      'path' => '/uploads/leads-cena/' . $fileName,
      'original_name' => $originalName,
    ];
  }

  public function form(): void
  {
    LeadDinnerEntry::ensureSchema();
    $msg = null;

    if (Helpers::isPost()) {
      Csrf::check();
      try {
        $firstName = trim((string)($_POST['first_name'] ?? ''));
        $lastName = trim((string)($_POST['last_name'] ?? ''));
        $whatsapp = trim((string)($_POST['whatsapp'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));

        if ($firstName === '' || $lastName === '') {
          throw new RuntimeException('Debes ingresar nombres y apellidos.');
        }
        if ($whatsapp === '') {
          throw new RuntimeException('Debes ingresar el WhatsApp.');
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
          throw new RuntimeException('Debes ingresar un correo valido.');
        }

        $upload = $this->uploadVoucher();
        $statusId = LeadDinnerStatus::firstActiveId();
        if ($statusId === null) {
          throw new RuntimeException('No hay estados activos configurados para leads-cena.');
        }

        LeadDinnerEntry::create([
          'first_name' => $firstName,
          'last_name' => $lastName,
          'whatsapp' => $whatsapp,
          'email' => $email,
          'voucher_path' => $upload['path'],
          'voucher_original_name' => $upload['original_name'],
          'status_id' => $statusId,
        ]);

        Helpers::redirect('/concurso/cena/gracias');
      } catch (Throwable $e) {
        $msg = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
      }
    }

    $this->view('public/lead_dinner_form', compact('msg'));
  }

  public function thankYou(): void
  {
    $this->view('public/lead_dinner_thank_you');
  }
}
