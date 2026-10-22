<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Csrf.php';
require_once __DIR__ . '/../core/Pagination.php';
require_once __DIR__ . '/../models/OtherExpense.php';

class OtherExpenseReceiptController extends Controller
{
  private function adminOnly(): void
  {
    Auth::requireLogin();
    if ((Auth::user()['role'] ?? '') !== 'admin') {
      http_response_code(403);
      exit('403 - Acceso exclusivo para administradores.');
    }
    OtherExpense::ensureSchema();
  }

  private static function filePath(string $name): string
  {
    if (!preg_match('/^[a-f0-9]{32}\.(png|pdf)$/D', $name)) throw new InvalidArgumentException('Archivo no válido.');
    return __DIR__ . '/../storage/other-expense-receipts/' . $name;
  }

  private function upload(bool $required): ?string
  {
    $file = $_FILES['receipt_file'] ?? [];
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error === UPLOAD_ERR_NO_FILE) {
      if ($required) throw new InvalidArgumentException('Adjunta el recibo en PNG o PDF.');
      return null;
    }
    if ($error !== UPLOAD_ERR_OK) throw new InvalidArgumentException('No se pudo cargar el archivo. Máximo 5 MB.');
    $tmp = $file['tmp_name'] ?? '';
    if (!is_string($tmp) || !is_uploaded_file($tmp)) throw new InvalidArgumentException('Carga no válida.');
    if (filesize($tmp) > 5 * 1024 * 1024 || filesize($tmp) === 0) throw new InvalidArgumentException('El archivo debe pesar entre 1 byte y 5 MB.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $ext = ['image/png'=>'png', 'application/pdf'=>'pdf'][$mime] ?? null;
    $valid = $mime === 'application/pdf' ? file_get_contents($tmp, false, null, 0, 5) === '%PDF-' : ($mime === 'image/png' && (@getimagesize($tmp)['mime'] ?? '') === 'image/png');
    if (!$ext || !$valid) throw new InvalidArgumentException('Adjunta un archivo PNG o PDF válido.');
    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    $path = self::filePath($name);
    if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0750, true) && !is_dir(dirname($path))) throw new RuntimeException('Error de almacenamiento.');
    if (!move_uploaded_file($tmp, $path)) throw new RuntimeException('Error al guardar archivo.');
    return $name;
  }

  private function removeFile(?string $name): void
  {
    if (!$name) return;
    $path = self::filePath($name);
    if (is_file($path) && !unlink($path)) error_log('No se pudo retirar un adjunto de recibos.');
  }

  public function save(): void
  {
    $this->adminOnly();
    if (!Helpers::isPost()) { http_response_code(405); header('Allow: POST'); return; }
    Csrf::check();
    $period = (string)($_POST['period'] ?? '');
    $date = DateTimeImmutable::createFromFormat('!Y-m', $period);
    if (!$date || $date->format('Y-m') !== $period) { http_response_code(422); exit('Periodo no válido.'); }
    $pdo = Database::conn(); $uploaded = null; $old = null;
    try {
      $action = (string)($_POST['action'] ?? '');
      if (!in_array($action, ['upload','delete'], true)) throw new InvalidArgumentException('Acción no válida.');
      $detailId = (int)($_POST['expense_detail_id'] ?? 0);
      $pdo->beginTransaction();
      $st = $pdo->prepare('SELECT id FROM expense_details WHERE id=? FOR UPDATE'); $st->execute([$detailId]);
      if (!$st->fetchColumn()) throw new InvalidArgumentException('El detalle de gasto no existe.');
      $st = $pdo->prepare('SELECT * FROM other_expense_receipts WHERE expense_detail_id=? AND period_month=? FOR UPDATE');
      $st->execute([$detailId,$date->format('Y-m-01')]); $current = $st->fetch();
      $old = $current['file_name'] ?? null;
      if ($action === 'delete') {
        if (!$current) throw new InvalidArgumentException('El recibo ya no existe.');
        $pdo->prepare('DELETE FROM other_expense_receipts WHERE id=?')->execute([$current['id']]);
      } else {
        $uploaded = $this->upload(true);
        $pdo->prepare('INSERT INTO other_expense_receipts (expense_detail_id,period_month,file_name) VALUES (?,?,?) ON DUPLICATE KEY UPDATE file_name=VALUES(file_name)')->execute([$detailId,$date->format('Y-m-01'),$uploaded]);
      }
      $pdo->commit();
      $this->removeFile($old);
      $_SESSION['other_expense_receipt_message'] = ['type'=>'success','text'=>$action === 'delete' ? 'Recibo eliminado.' : 'Recibo guardado correctamente.'];
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $this->removeFile($uploaded);
      $_SESSION['other_expense_receipt_message'] = ['type'=>'danger','text'=>$e instanceof InvalidArgumentException ? $e->getMessage() : 'No se pudo guardar el recibo. Inténtalo nuevamente.'];
      if (!$e instanceof InvalidArgumentException) error_log('Error en adjunto de otros gastos: '.get_class($e));
    }
    Helpers::redirect('/admin/finance/other-expenses?year='.$date->format('Y').'&month='.$date->format('n'));
  }

  public function file(): void
  {
    $this->adminOnly();
    $st = Database::conn()->prepare('SELECT * FROM other_expense_receipts WHERE id=?');
    $st->execute([(int)($_GET['id'] ?? 0)]); $record = $st->fetch();
    if (!$record || !is_file(self::filePath($record['file_name']))) { http_response_code(404); exit('Archivo no encontrado.'); }
    $path = self::filePath($record['file_name']); $ext = pathinfo($path, PATHINFO_EXTENSION);
    header('Content-Type: '.($ext === 'pdf' ? 'application/pdf' : 'image/png'));
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: sandbox; default-src 'none'; style-src 'unsafe-inline'");
    header('Cache-Control: private, no-store');
    header('Content-Length: '.filesize($path));
    header('Content-Disposition: inline; filename="recibo-gasto-'.(int)$record['id'].'.'.$ext.'"');
    readfile($path);
  }
}
