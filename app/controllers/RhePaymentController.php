<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Csrf.php';
require_once __DIR__ . '/../core/Pagination.php';
require_once __DIR__ . '/../models/RhePayment.php';
require_once __DIR__ . '/../models/User.php';

class RhePaymentController extends Controller
{
  private static function imagePath(string $name): string
  {
    if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp|pdf)$/D', $name)) {
      throw new InvalidArgumentException('Archivo no válido.');
    }
    return __DIR__ . '/../storage/rhe/' . $name;
  }

  private function upload(bool $required): ?string
  {
    $file = $_FILES['image'] ?? [];
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error === UPLOAD_ERR_NO_FILE) {
      if ($required) throw new InvalidArgumentException('Adjunta una imagen o un PDF.');
      return null;
    }
    if ($error !== UPLOAD_ERR_OK) throw new InvalidArgumentException('No se pudo cargar el archivo. Revisa el tamaño e inténtalo otra vez.');
    $tmp = $file['tmp_name'] ?? '';
    if (!is_string($tmp) || !is_uploaded_file($tmp)) throw new InvalidArgumentException('Carga de archivo no válida.');
    if (filesize($tmp) > 5 * 1024 * 1024) throw new InvalidArgumentException('El archivo debe pesar como máximo 5 MB.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
    if ($mime === 'application/pdf') {
      $valid = file_get_contents($tmp, false, null, 0, 5) === '%PDF-';
    } else {
      $info = @getimagesize($tmp);
      $valid = $info && ($info['mime'] ?? '') === $mime;
    }
    if (!isset($extensions[$mime]) || !$valid) {
      throw new InvalidArgumentException('Adjunta un archivo JPG, PNG, WebP o PDF válido.');
    }
    $name = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    $path = self::imagePath($name);
    if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0750, true) && !is_dir(dirname($path))) {
      throw new RuntimeException('No se pudo preparar el almacenamiento.');
    }
    if (!move_uploaded_file($tmp, $path)) throw new RuntimeException('No se pudo guardar el archivo.');
    return $name;
  }

  private function removeImage(?string $name): void
  {
    if (!$name) return;
    $path = self::imagePath($name);
    if (is_file($path) && !unlink($path)) error_log('No se pudo retirar un adjunto RHE.');
  }

  public function index(): void
  {
    Auth::requireRole('admin');
    $msg = $_SESSION['rhe_message'] ?? null;
    unset($_SESSION['rhe_message']);
    $form = ['id' => 0, 'amount' => '', 'user_id' => '', 'month' => date('Y-m'), 'image_name' => '', 'temporary_first_name' => '', 'temporary_last_name' => '', 'worker_type' => 'active'];
    $editId = max(0, (int)($_GET['edit_id'] ?? 0));
    if ($editId) {
      $record = RhePayment::find($editId);
      if ($record) $form = ['id' => $record['id'], 'amount' => $record['amount'] ?? '', 'user_id' => $record['user_id'], 'month' => substr($record['period_month'], 0, 7), 'image_name' => $record['image_name'], 'temporary_first_name' => $record['temporary_first_name'] ?? '', 'temporary_last_name' => $record['temporary_last_name'] ?? '', 'worker_type' => $record['user_id'] === null ? 'temporary' : 'active'];
      else $msg = ['type' => 'warning', 'text' => 'El registro ya no existe.'];
    }
    if (Helpers::isPost()) {
      Csrf::check();
      $uploaded = null;
      $pdo = Database::conn();
      try {
        $action = (string)($_POST['action'] ?? '');
        if (!in_array($action, ['save', 'delete'], true)) throw new InvalidArgumentException('Acción no válida.');
        $id = max(0, (int)($_POST['id'] ?? 0));
        $pdo->beginTransaction();
        $current = null;
        if ($id) {
          $st = $pdo->prepare('SELECT * FROM rhe_payments WHERE id=? FOR UPDATE');
          $st->execute([$id]);
          $current = $st->fetch();
          if (!$current) throw new InvalidArgumentException('El registro ya no existe.');
        }
        if ($action === 'delete') {
          if (!$current) throw new InvalidArgumentException('Selecciona un registro.');
          RhePayment::delete($id);
          $success = 'Pago RHE eliminado.';
        } else {
          $form = ['id' => $id, 'amount' => trim((string)($_POST['amount'] ?? '')), 'user_id' => (int)($_POST['user_id'] ?? 0), 'month' => (string)($_POST['month'] ?? ''), 'image_name' => $current['image_name'] ?? '', 'worker_type' => (string)($_POST['worker_type'] ?? 'active'), 'temporary_first_name' => trim((string)($_POST['temporary_first_name'] ?? '')), 'temporary_last_name' => trim((string)($_POST['temporary_last_name'] ?? ''))];
          $month = RhePayment::validateMonth($form['month']);
          $amount = RhePayment::validateAmount($form['amount']);
          if (!in_array($form['worker_type'], ['active', 'temporary'], true)) throw new InvalidArgumentException('Tipo de trabajador no válido.');
          if ($current && (($current['user_id'] === null) !== ($form['worker_type'] === 'temporary'))) throw new InvalidArgumentException('No se puede cambiar el tipo de trabajador del registro.');
          $temporary = $form['worker_type'] === 'temporary';
          if ($temporary) {
            foreach (['temporary_first_name', 'temporary_last_name'] as $field) {
              if ($form[$field] === '' || !preg_match('/^[\p{L}\p{M} .\x{0027}\x{2019}-]{1,100}$/uD', $form[$field])) {
                throw new InvalidArgumentException('Ingresa nombres y apellidos válidos, de hasta 100 caracteres cada uno.');
              }
            }
          } else {
            $st = $pdo->prepare("SELECT id FROM users WHERE id=? AND role='worker' AND is_active=1");
            $st->execute([$form['user_id']]);
            if (!$st->fetchColumn()) throw new InvalidArgumentException('Selecciona un trabajador activo.');
          }
          $uploaded = $this->upload(!$current);
          RhePayment::save($id, $temporary ? null : $form['user_id'], $month, $uploaded ?? $current['image_name'], $amount,
            $temporary ? $form['temporary_first_name'] : null, $temporary ? $form['temporary_last_name'] : null);
          $success = $id ? 'Pago RHE actualizado.' : 'Pago RHE guardado.';
        }
        $pdo->commit();
        if ($current && ($uploaded || $action === 'delete')) $this->removeImage($current['image_name']);
        $_SESSION['rhe_message'] = ['type' => 'success', 'text' => $success];
        Helpers::redirect('/admin/rhe-payments');
      } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
          $pdo->rollBack();
          $this->removeImage($uploaded);
        }
        $msg = ['type' => 'danger', 'text' => $e instanceof InvalidArgumentException ? $e->getMessage() : 'No se pudo guardar el cambio. Inténtalo nuevamente.'];
        if (!($e instanceof InvalidArgumentException)) error_log('Error al procesar Pago RHE: ' . get_class($e));
      }
    }
    $workers = Database::conn()->query("SELECT id, document_number, first_name, last_name FROM users WHERE role='worker' AND is_active=1 ORDER BY first_name, last_name")->fetchAll();
    $pagination = RhePayment::paginate();
    $this->view('admin/rhe_payments', compact('form', 'workers', 'pagination', 'msg'));
  }

  public function image(): void
  {
    Auth::requireRole('admin');
    $record = RhePayment::find((int)($_GET['id'] ?? 0));
    if (!$record || !is_file(self::imagePath($record['image_name']))) {
      http_response_code(404);
      exit('Archivo no encontrado.');
    }
    $path = self::imagePath($record['image_name']);
    $mime = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'pdf' => 'application/pdf'][pathinfo($path, PATHINFO_EXTENSION)];
    header('Content-Type: ' . $mime);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: inline; filename="rhe-' . (int)$record['id'] . '.' . pathinfo($path, PATHINFO_EXTENSION) . '"');
    readfile($path);
  }
}
