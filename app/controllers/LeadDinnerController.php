<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Csrf.php';
require_once __DIR__ . '/../models/LeadDinnerEntry.php';
require_once __DIR__ . '/../models/LeadDinnerStatus.php';

class LeadDinnerController extends Controller
{
  private function uploadVoucher(): array
  {
    $file = $_FILES['voucher'] ?? null;
    if (!$file || !is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
      throw new RuntimeException('Debes adjuntar el voucher de consumo.');
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
      throw new RuntimeException('El voucher subido no es valido.');
    }

    $originalName = (string)($file['name'] ?? 'voucher');
    $ext = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    if (!in_array($ext, $allowed, true)) {
      throw new RuntimeException('El voucher debe ser JPG, PNG, WEBP o PDF.');
    }

    $dir = dirname(__DIR__, 2) . '/uploads/leads-cena';
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
      throw new RuntimeException('No se pudo crear la carpeta para vouchers.');
    }

    $fileName = 'voucher_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
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
