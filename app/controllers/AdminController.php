<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Csrf.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Shift.php';
require_once __DIR__ . '/../models/Attendance.php';
require_once __DIR__ . '/../models/Payroll.php';
require_once __DIR__ . '/../models/WorkArea.php';
require_once __DIR__ . '/../models/PurchaseArea.php';
require_once __DIR__ . '/../models/Supply.php';
require_once __DIR__ . '/../models/UnitMeasure.php';
require_once __DIR__ . '/../models/Requirement.php';
require_once __DIR__ . '/../models/Activity.php';
require_once __DIR__ . '/../models/Task.php';
require_once __DIR__ . '/../models/WorkerPayRate.php';
require_once __DIR__ . '/../models/Promotion.php';
require_once __DIR__ . '/../models/InventoryItem.php';
require_once __DIR__ . '/../core/InventoryImage.php';
require_once __DIR__ . '/../models/BeverageControl.php';
require_once __DIR__ . '/../models/ProductCategory.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/DeliveryPricing.php';
require_once __DIR__ . '/../models/Incentive.php';
require_once __DIR__ . '/../models/JobFunction.php';
require_once __DIR__ . '/../models/Costing.php';
require_once __DIR__ . '/../models/Recipe.php';
require_once __DIR__ . '/../models/MonthlyProductSale.php';
require_once __DIR__ . '/../models/SalesImportAudit.php';
require_once __DIR__ . '/../models/MailNotificationLog.php';
require_once __DIR__ . '/../models/LeadDinnerStatus.php';
require_once __DIR__ . '/../models/LeadDinnerEntry.php';
require_once __DIR__ . '/../models/LeadDinnerCampaign.php';
require_once __DIR__ . '/../models/FinanceSummary.php';
require_once __DIR__ . '/../models/OtherExpense.php';
require_once __DIR__ . '/../core/XlsxReader.php';

class AdminController extends Controller
{
  private function isJsonRequest(): bool
  {
    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    $requestedWith = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));

    return str_contains($accept, 'application/json') || $requestedWith === 'xmlhttprequest';
  }

  private function jsonResponse(array $payload, int $statusCode = 200): void
  {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }

  private function spreadsheetRowsToAssoc(array $rows): array
  {
    if (count($rows) < 2) {
      return [];
    }

    $headers = array_map(static fn($value) => trim((string)$value), $rows[0]);
    $items = [];

    for ($i = 1; $i < count($rows); $i++) {
      $row = $rows[$i];
      $assoc = [];

      foreach ($headers as $index => $header) {
        if ($header === '') {
          continue;
        }
        $assoc[$header] = isset($row[$index]) ? trim((string)$row[$index]) : '';
      }

      if (implode('', $assoc) !== '') {
        $items[] = $assoc;
      }
    }

    return $items;
  }

  private function uploadedXlsxPath(string $fieldName): array
  {
    $file = $_FILES[$fieldName] ?? null;
    if (!$file || !is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
      throw new RuntimeException('Debes subir un archivo .xlsx valido.');
    }

    $name = (string)($file['name'] ?? '');
    if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'xlsx') {
      throw new RuntimeException('Solo se permiten archivos .xlsx.');
    }
    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
      throw new RuntimeException('El archivo subido no es valido.');
    }
    if ((int)($file['size'] ?? 0) <= 0 || (int)$file['size'] > 10 * 1024 * 1024) {
      throw new RuntimeException('El archivo debe pesar como maximo 10 MB.');
    }
    $signature = file_get_contents($tmp, false, null, 0, 4);
    if ($signature !== "PK\x03\x04") {
      throw new RuntimeException('El contenido no corresponde a un archivo .xlsx valido.');
    }

    return [
      'tmp_name' => $tmp,
      'name' => $name,
    ];
  }

  private function uploadLeadDinnerVoucher(string $fieldName, bool $required = true): ?array
  {
    $file = $_FILES[$fieldName] ?? null;
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if (!$file || !is_array($file) || $error === UPLOAD_ERR_NO_FILE) {
      if ($required) {
        throw new RuntimeException('Debes adjuntar el voucher de consumo.');
      }
      return null;
    }

    if ($error !== UPLOAD_ERR_OK) {
      throw new RuntimeException('No se pudo procesar el voucher adjunto.');
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
      throw new RuntimeException('El voucher subido no es valido.');
    }
    if ((int)($file['size'] ?? 0) <= 0 || (int)$file['size'] > 10 * 1024 * 1024) {
      throw new RuntimeException('El voucher debe pesar como maximo 10 MB.');
    }

    $originalName = (string)($file['name'] ?? 'voucher');
    $extension = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
    $allowed = [
      'jpg' => 'jpg',
      'jpeg' => 'jpg',
      'png' => 'png',
      'webp' => 'webp',
      'pdf' => 'pdf',
      'heic' => 'heic',
      'heif' => 'heif',
    ];
    if (!isset($allowed[$extension])) {
      throw new RuntimeException('El voucher debe ser JPG, PNG, WEBP, HEIC, HEIF o PDF.');
    }
    $mime = '';
    if (function_exists('finfo_open')) {
      $finfo = finfo_open(FILEINFO_MIME_TYPE);
      if ($finfo !== false) {
        $mime = strtolower((string)finfo_file($finfo, $tmp));
        finfo_close($finfo);
      }
    }
    $allowedMimes = [
      'jpg' => ['image/jpeg'],
      'jpeg' => ['image/jpeg'],
      'png' => ['image/png'],
      'webp' => ['image/webp'],
      'pdf' => ['application/pdf'],
      'heic' => ['image/heic', 'image/heic-sequence', 'application/octet-stream'],
      'heif' => ['image/heif', 'image/heif-sequence', 'application/octet-stream'],
    ];
    if ($mime !== '' && !in_array($mime, $allowedMimes[$extension] ?? [], true)) {
      throw new RuntimeException('El contenido del voucher no coincide con su extension.');
    }

    $directory = dirname(__DIR__, 2) . '/uploads/leads-cena';
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
      throw new RuntimeException('No se pudo crear la carpeta para vouchers.');
    }

    $fileName = 'voucher_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $allowed[$extension];
    $target = $directory . '/' . $fileName;
    if (!move_uploaded_file($tmp, $target)) {
      throw new RuntimeException('No se pudo guardar el voucher.');
    }

    return [
      'path' => '/uploads/leads-cena/' . $fileName,
      'original_name' => $originalName,
      'absolute_path' => $target,
    ];
  }

  private function leadDinnerData(array $source): array
  {
    $firstName = trim((string)($source['first_name'] ?? ''));
    $lastName = trim((string)($source['last_name'] ?? ''));
    $whatsapp = trim((string)($source['whatsapp'] ?? ''));
    $email = trim((string)($source['email'] ?? ''));
    $statusId = (int)($source['status_id'] ?? 0);
    $campaignId = (int)($source['campaign_id'] ?? 0);

    if ($firstName === '' || $lastName === '') {
      throw new RuntimeException('Debes ingresar nombres y apellidos.');
    }
    if ($whatsapp === '') {
      throw new RuntimeException('Debes ingresar el WhatsApp.');
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      throw new RuntimeException('El correo ingresado no es valido.');
    }
    if ($statusId <= 0) {
      throw new RuntimeException('Debes seleccionar un estado.');
    }
    $campaign = LeadDinnerCampaign::find($campaignId);
    if (!$campaign || (int)$campaign['is_active'] !== 1) {
      throw new RuntimeException('Debes seleccionar una campaña activa.');
    }

    return compact('firstName', 'lastName', 'whatsapp', 'email', 'statusId', 'campaignId');
  }

  private function deleteLeadDinnerVoucher(?string $voucherPath): void
  {
    $voucherPath = trim((string)$voucherPath);
    if (!str_starts_with($voucherPath, '/uploads/leads-cena/')) {
      return;
    }

    $absolutePath = dirname(__DIR__, 2) . str_replace('/', DIRECTORY_SEPARATOR, $voucherPath);
    if (is_file($absolutePath)) {
      unlink($absolutePath);
    }
  }

  private function resolveSalesPeriodMonth(string $fileName, ?string $rawMonth): string
  {
    $rawMonth = trim((string)$rawMonth);
    if ($rawMonth !== '') {
      $date = DateTime::createFromFormat('Y-m', $rawMonth);
      if (!$date) {
        throw new RuntimeException('El mes seleccionado no es valido.');
      }
      return $date->format('Y-m-01');
    }

    if (preg_match_all('/(\d{2})_(\d{2})_(\d{4})/', $fileName, $matches, PREG_SET_ORDER) >= 1) {
      $first = DateTime::createFromFormat('d_m_Y', $matches[0][0]);
      $last = count($matches) > 1 ? DateTime::createFromFormat('d_m_Y', $matches[count($matches) - 1][0]) : $first;

      if (!$first || !$last) {
        throw new RuntimeException('No se pudo detectar el mes del archivo.');
      }

      if ($first->format('Y-m') !== $last->format('Y-m')) {
        throw new RuntimeException('El archivo debe corresponder a un solo mes.');
      }

      return $first->format('Y-m-01');
    }

    throw new RuntimeException('Selecciona el mes de ventas a importar.');
  }

  private function importInventoryCatalog(array $upload): int
  {
    $rows = $this->spreadsheetRowsToAssoc(XlsxReader::rows($upload['tmp_name']));
    $count = 0;

    foreach ($rows as $row) {
      if (trim((string)($row['PRODUCTO'] ?? '')) === '') {
        continue;
      }
      Product::upsertFromInventoryRow($row);
      $count++;
    }

    return $count;
  }

  private function importMonthlySales(array $upload, ?string $rawMonth): array
  {
    $rows = $this->spreadsheetRowsToAssoc(XlsxReader::rows($upload['tmp_name']));
    $requiredColumns = ['PRODUCTO', 'CATEGORIA', 'UNIDADES', 'PRECIO UNITARIO', 'VENTA TOTAL'];
    $firstRow = $rows[0] ?? [];
    $missingColumns = array_values(array_filter(
      $requiredColumns,
      static fn(string $column): bool => !array_key_exists($column, $firstRow)
    ));
    if ($missingColumns) {
      throw new RuntimeException('Faltan columnas obligatorias: ' . implode(', ', $missingColumns) . '.');
    }

    foreach ($rows as $index => $row) {
      if (trim((string)($row['PRODUCTO'] ?? '')) !== '' && trim((string)($row['CATEGORIA'] ?? '')) === '') {
        throw new RuntimeException('La categoria es obligatoria para el producto de la fila ' . ($index + 2) . '.');
      }
    }

    $periodMonth = $this->resolveSalesPeriodMonth($upload['name'], $rawMonth);
    $result = MonthlyProductSale::replaceMonthFromRows($periodMonth, $rows, $upload['name']);

    return [
      'count' => $result['count'],
      'period_month' => $periodMonth,
      'audit_id' => $result['audit_id'],
      'issues_count' => $result['issues_count'],
      'raw_total_amount' => $result['raw_total_amount'],
      'normalized_total_amount' => $result['normalized_total_amount'],
    ];
  }

  private function calculateMinutesLate(int $userId, string $type, DateTime $markedAt): int
  {
    if ($type !== 'in') {
      return 0;
    }

    $user = User::find($userId);
    if (!$user || empty($user['shift_id'])) {
      return 0;
    }

    $shift = Shift::find((int)$user['shift_id']);
    if (!$shift) {
      return 0;
    }

    $start = DateTime::createFromFormat('H:i:s', $shift['start_time']);
    if (!$start) {
      return 0;
    }

    $start->setDate((int)$markedAt->format('Y'), (int)$markedAt->format('m'), (int)$markedAt->format('d'));
    if ($markedAt <= $start) {
      return 0;
    }

    $diff = $start->diff($markedAt);
    return ($diff->h * 60) + $diff->i;
  }

  public function dashboard(): void
  {
    Auth::requireRole('admin');
    $workersCount = User::countWorkers();
    $latest = Attendance::latest(12);
    $this->view('admin/dashboard', compact('workersCount', 'latest'));
  }

  public function profile(): void
  {
    Auth::requireRole('admin');
    $user = Auth::user();
    $this->view('admin/profile', compact('user'));
  }

  public function workers(): void
  {
    Auth::requireRole('admin');

    $msg = null;

    if (Helpers::isPost()) {
      Csrf::check();
      $action = $_POST['action'] ?? '';

      try {
        if ($action === 'create') {
          $newId = User::createWorker([
            'document_type' => $_POST['document_type'] ?? 'dni',
            'document_number' => trim($_POST['document_number'] ?? ''),
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'shift_id' => (int)($_POST['shift_id'] ?? 0),
            'area_id' => (int)($_POST['area_id'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'password' => $_POST['password'] ?? ''
          ]);
          // Pago diario (opcional)
          $dailyRate = isset($_POST['daily_rate']) && $_POST['daily_rate'] !== '' ? (float)$_POST['daily_rate'] : null;
          WorkerPayRate::upsert((int)$newId, $dailyRate);

          $msg = ['type' => 'success', 'text' => 'Trabajador creado'];
        }

        if ($action === 'update') {
          User::updateWorker((int)$_POST['id'], [
            'document_type' => $_POST['document_type'] ?? 'dni',
            'document_number' => trim($_POST['document_number'] ?? ''),
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'shift_id' => (int)($_POST['shift_id'] ?? 0),
            'area_id' => (int)($_POST['area_id'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'password' => $_POST['password'] ?? ''
          ]);
          $dailyRate = isset($_POST['daily_rate']) && $_POST['daily_rate'] !== '' ? (float)$_POST['daily_rate'] : null;
          WorkerPayRate::upsert((int)$_POST['id'], $dailyRate);

          $msg = ['type' => 'success', 'text' => 'Trabajador actualizado'];
        }

        if ($action === 'delete') {
          WorkerPayRate::upsert((int)$_POST['id'], null);
          User::deleteWorker((int)$_POST['id']);
          $msg = ['type' => 'warning', 'text' => 'Trabajador eliminado'];
        }
      } catch (Throwable $e) {
        $msg = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
      }
    }

    $workers = User::allWorkers();
    $shifts = Shift::all();
    $areas = WorkArea::all();
    $this->view('admin/workers', compact('workers', 'shifts', 'areas', 'msg'));
  }

  public function shifts(): void
  {
    Auth::requireRole('admin');
    $msg = null;

    if (Helpers::isPost()) {
      Csrf::check();
      $action = $_POST['action'] ?? '';

      try {
        if ($action === 'create') {
          Shift::create(trim($_POST['name']), $_POST['start_time'], $_POST['end_time']);
          $msg = ['type' => 'success', 'text' => 'Turno creado'];
        }
        if ($action === 'update') {
          Shift::update((int)$_POST['id'], trim($_POST['name']), $_POST['start_time'], $_POST['end_time']);
          $msg = ['type' => 'success', 'text' => 'Turno actualizado'];
        }
        if ($action === 'delete') {
          Shift::delete((int)$_POST['id']);
          $msg = ['type' => 'warning', 'text' => 'Turno eliminado'];
        }
      } catch (Throwable $e) {
        $msg = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
      }
    }

    $shifts = Shift::all();
    $this->view('admin/shifts', compact('shifts', 'msg'));
  }


  public function areas(): void
  {
    Auth::requireRole('admin');
    $msg = null;

    if (Helpers::isPost()) {
      Csrf::check();
      $action = $_POST['action'] ?? '';

      try {
        if ($action === 'create') {
          WorkArea::create(trim($_POST['name']));
          $msg = ['type' => 'success', 'text' => 'Área creada'];
        }
        if ($action === 'update') {
          WorkArea::update((int)$_POST['id'], trim($_POST['name']));
          $msg = ['type' => 'success', 'text' => 'Área actualizada'];
        }
        if ($action === 'delete') {
          WorkArea::delete((int)$_POST['id']);
          $msg = ['type' => 'warning', 'text' => 'Área eliminada'];
        }
      } catch (Throwable $e) {
        $msg = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
      }
    }

    $areas = WorkArea::all();
    $this->view('admin/areas', compact('areas', 'msg'));
  }

  public function purchaseAreas(): void
  {
    Auth::requireRole('admin');
    $msg = null;

    if (Helpers::isPost()) {
      Csrf::check();
      $action = $_POST['action'] ?? '';

      try {
        if ($action === 'create') {
          PurchaseArea::create(trim((string)$_POST['name']), isset($_POST['is_active']) ? 1 : 0);
          $msg = ['type' => 'success', 'text' => 'Area de compra creada'];
        }
        if ($action === 'update') {
          PurchaseArea::update((int)$_POST['id'], trim((string)$_POST['name']));
          $msg = ['type' => 'success', 'text' => 'Area de compra actualizada'];
        }
        if ($action === 'activate') {
          PurchaseArea::setActive((int)$_POST['id'], 1);
          $msg = ['type' => 'success', 'text' => 'Area de compra activada'];
        }
        if ($action === 'deactivate') {
          PurchaseArea::setActive((int)$_POST['id'], 0);
          $msg = ['type' => 'warning', 'text' => 'Area de compra desactivada'];
        }
      } catch (Throwable $e) {
        $msg = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
      }
    }

    $areas = PurchaseArea::all();
    $this->view('admin/purchase_areas', compact('areas', 'msg'));
  }

  public function supplies(): void
  {
    Auth::requireRole('admin');
    $msg = null;

    if (Helpers::isPost()) {
      Csrf::check();
      $action = $_POST['action'] ?? '';

      try {
        if ($action === 'create') {
          Supply::create($_POST);
          $msg = ['type' => 'success', 'text' => 'Insumo registrado'];
        }
        if ($action === 'update') {
          Supply::update((int)($_POST['id'] ?? 0), $_POST);
          $msg = ['type' => 'success', 'text' => 'Insumo actualizado'];
        }
        if ($action === 'activate') {
          Supply::setActive((int)($_POST['id'] ?? 0), 1);
          $msg = ['type' => 'success', 'text' => 'Insumo activado'];
        }
        if ($action === 'deactivate') {
          Supply::setActive((int)($_POST['id'] ?? 0), 0);
          $msg = ['type' => 'warning', 'text' => 'Insumo desactivado'];
        }
        } catch (Throwable $e) {
          $message = $e->getMessage();
          if (str_contains($message, 'uq_supplies_area_name') || str_contains($message, 'Duplicate entry')) {
            $message = 'Ya existe un insumo con ese nombre en una de las areas seleccionadas.';
          }
          $msg = ['type' => 'danger', 'text' => 'Error: ' . $message];
        }
    }

    $search = trim((string)($_GET['search'] ?? ''));
    $purchaseAreaId = (int)($_GET['purchase_area_id'] ?? 0);
    $purchaseAreaId = $purchaseAreaId > 0 ? $purchaseAreaId : null;
    $rawStatus = (string)($_GET['status'] ?? '');
    $status = $rawStatus === '' ? null : (int)$rawStatus;
    $page = (int)($_GET['supplies_page'] ?? 1);
    $perPage = (int)($_GET['supplies_per_page'] ?? 10);

    $purchaseAreas = PurchaseArea::all();
    $unitMeasures = UnitMeasure::active();
    $suppliesPagination = Supply::paginate($search, $purchaseAreaId, $status, $page, $perPage);
    $supplies = $suppliesPagination['rows'];
    $suppliesPaginationMeta = $suppliesPagination['meta'];

    $this->view('admin/supplies', compact(
      'msg',
      'purchaseAreas',
      'unitMeasures',
      'supplies',
      'suppliesPaginationMeta',
      'search',
      'purchaseAreaId',
      'rawStatus'
      ));
    }

  public function unitMeasures(): void
  {
    Auth::requireRole('admin');
    $msg = null;

    if (Helpers::isPost()) {
      Csrf::check();
      $action = $_POST['action'] ?? '';

      try {
        if ($action === 'create') {
          UnitMeasure::create($_POST);
          $msg = ['type' => 'success', 'text' => 'Unidad de medida registrada'];
        }
        if ($action === 'update') {
          UnitMeasure::update((int)($_POST['id'] ?? 0), $_POST);
          $msg = ['type' => 'success', 'text' => 'Unidad de medida actualizada'];
        }
        if ($action === 'activate') {
          UnitMeasure::setActive((int)($_POST['id'] ?? 0), 1);
          $msg = ['type' => 'success', 'text' => 'Unidad de medida activada'];
        }
        if ($action === 'deactivate') {
          UnitMeasure::setActive((int)($_POST['id'] ?? 0), 0);
          $msg = ['type' => 'warning', 'text' => 'Unidad de medida desactivada'];
        }
      } catch (Throwable $e) {
        $message = $e->getMessage();
        if (str_contains($message, 'uq_unit_measures_normalized_name') || str_contains($message, 'Duplicate entry')) {
          $message = 'Ya existe una unidad de medida con ese nombre.';
        }
        $msg = ['type' => 'danger', 'text' => 'Error: ' . $message];
      }
    }

    $units = UnitMeasure::all();
    $this->view('admin/unit_measures', compact('units', 'msg'));
  }

  public function requirements(): void
  {
    Auth::requireRole('admin');
    $msg = null;
    $selectedWeekStart = Requirement::normalizeWeekStart(($_GET['week_start'] ?? '') === 'all' ? null : ($_GET['week_start'] ?? null));
    $expectsJson = $this->isJsonRequest();

    if (Helpers::isPost()) {
      Csrf::check();
      $action = $_POST['action'] ?? '';

      try {
        if ($action === 'toggle_item') {
          $isPurchased = isset($_POST['is_purchased']) ? 1 : 0;
          $purchasedAt = Requirement::setPurchased(
            (int)($_POST['item_id'] ?? 0),
            $isPurchased
          );

          if ($expectsJson) {
            $this->jsonResponse([
              'ok' => true,
              'message' => 'Estado de compra actualizado',
              'item' => [
                'id' => (int)($_POST['item_id'] ?? 0),
                'is_purchased' => $isPurchased,
                'status_text' => $isPurchased === 1 ? 'Comprado' : 'Pendiente',
                'status_class' => $isPurchased === 1 ? 'success' : 'secondary',
                'purchased_at' => $purchasedAt,
                'purchased_at_text' => $purchasedAt !== null
                  ? 'Fecha completado: ' . date('d/m/Y', strtotime($purchasedAt))
                    . ' - Hora completado: ' . date('H:i', strtotime($purchasedAt))
                  : null,
              ],
            ]);
          }

          $msg = ['type' => 'success', 'text' => 'Estado de compra actualizado'];
        }

        if ($action === 'delete_item') {
          Requirement::deleteItem((int)($_POST['item_id'] ?? 0));
          $msg = ['type' => 'warning', 'text' => 'Item eliminado del requerimiento'];
        }

        if ($action === 'create_requirement') {
          $workerId = (int)($_POST['user_id'] ?? 0);
          $requiredDate = trim((string)($_POST['required_date'] ?? ''));
          $itemsRaw = (array)($_POST['items'] ?? []);
          $supplyIdsRaw = (array)($_POST['supply_ids'] ?? []);
          $quantitiesRaw = (array)($_POST['quantities'] ?? []);
          $unitMeasureIdsRaw = (array)($_POST['unit_measure_ids'] ?? []);
          $detailsRaw = (array)($_POST['details'] ?? []);
          $sanitized = Requirement::sanitizeStructuredItems($itemsRaw, $supplyIdsRaw, $quantitiesRaw, $unitMeasureIdsRaw, 0, $detailsRaw);
          $items = $sanitized['items'];

          $worker = User::findWithDetails($workerId);
          if (!$worker || !in_array(($worker['role'] ?? ''), ['admin', 'worker'], true)) {
            throw new RuntimeException('Debes seleccionar un trabajador o administrador valido.');
          }
          $date = DateTime::createFromFormat('Y-m-d', $requiredDate);
          if (!$date || $date->format('Y-m-d') !== $requiredDate) {
            throw new RuntimeException('Debes seleccionar una fecha valida.');
          }
          if (empty($items)) {
            throw new RuntimeException('Debes ingresar al menos un producto.');
          }
          if (!empty($sanitized['duplicates'])) {
            throw new RuntimeException('No puedes repetir productos en el mismo registro: ' . implode(', ', $sanitized['duplicates']));
          }

          $itemsByArea = [];
          foreach ($items as $item) {
            $areaId = (int)($item['purchase_area_id'] ?? 0);
            if ($areaId <= 0) {
              throw new RuntimeException('No se pudo determinar el area de compra para ' . $item['item_name'] . '.');
            }
            $itemsByArea[$areaId][] = $item;
          }

          foreach ($itemsByArea as $areaId => $areaItems) {
            $existingDuplicates = Requirement::duplicateItemsForWorkerSlot($workerId, (int)$areaId, $requiredDate, $areaItems);
            if (!empty($existingDuplicates)) {
              throw new RuntimeException('Estos productos ya fueron registrados para su area y fecha: ' . implode(', ', $existingDuplicates));
            }
          }

          $requirementIds = [];
          foreach ($itemsByArea as $areaId => $areaItems) {
            $requirementIds[] = Requirement::create($workerId, (int)$areaId, $requiredDate, $areaItems, 'submitted');
          }
          $selectedWeekStart = Requirement::normalizeWeekStart($requiredDate);
          $msg = ['type' => 'success', 'text' => count($requirementIds) === 1
            ? 'Requerimiento registrado #' . $requirementIds[0]
            : count($requirementIds) . ' requerimientos registrados por area'];
        }
      } catch (Throwable $e) {
        if ($expectsJson) {
          $this->jsonResponse([
            'ok' => false,
            'message' => 'Error: ' . $e->getMessage(),
          ], 422);
        }

        $msg = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
      }
    }

    $week = Requirement::weekRangeForDate($selectedWeekStart);
    $allWeeks = ($_GET['week_start'] ?? '') === 'all';
    $purchaseStatus = (string)($_GET['purchase_status'] ?? 'all');
    if (!in_array($purchaseStatus, ['all', 'pending', 'purchased'], true)) $purchaseStatus = 'all';
    $rows = Requirement::forAdminWeek($allWeeks ? null : $week['from'], $purchaseStatus === 'all' ? null : ($purchaseStatus === 'purchased' ? 1 : 0));
      $weekOptions = Requirement::weekOptions(8);
      $workers = User::allRequirementUsers();
      $supplies = Supply::activeForRequirements();
      $unitMeasures = UnitMeasure::active();
      $today = date('Y-m-d');
    $defaultRequirementDate = ($today >= $week['from'] && $today <= $week['to']) ? $today : $week['from'];
    $mailLogs = array_slice($this->requirementMailLogs(), 0, 10);
    $grouped = [];
    $weeklyEstimatedTotal = 0.0;
    $weeklyPurchasedTotal = 0.0;
    $weeklyUnpricedItems = 0;

    foreach ($rows as $row) {
      $row['subtotal'] = Requirement::calculateSubtotal($row);

      if ($row['subtotal'] === null) {
        $weeklyUnpricedItems++;
      } else {
        $weeklyEstimatedTotal += $row['subtotal'];
        if ((int)$row['is_purchased'] === 1) {
          $weeklyPurchasedTotal += $row['subtotal'];
        }
      }

      $workerKey = (int)$row['user_id'];
      if (!isset($grouped[$workerKey])) {
        $grouped[$workerKey] = [
          'worker_name' => trim($row['first_name'] . ' ' . $row['last_name']),
          'user_role' => $row['user_role'] ?? 'worker',
          'estimated_total' => 0.0,
          'purchased_total' => 0.0,
          'areas' => []
        ];
      }

      $areaKey = $row['required_date'] . '|' . $row['purchase_area_name'];
      if (!isset($grouped[$workerKey]['areas'][$areaKey])) {
        $grouped[$workerKey]['areas'][$areaKey] = [
          'required_date' => $row['required_date'],
          'purchase_area_name' => $row['purchase_area_name'],
          'status' => $row['status'] ?? 'submitted',
          'estimated_total' => 0.0,
          'purchased_total' => 0.0,
          'items' => []
        ];
      }

      $grouped[$workerKey]['areas'][$areaKey]['items'][] = $row;
      if ($row['subtotal'] !== null) {
        $grouped[$workerKey]['estimated_total'] += $row['subtotal'];
        $grouped[$workerKey]['areas'][$areaKey]['estimated_total'] += $row['subtotal'];
        if ((int)$row['is_purchased'] === 1) {
          $grouped[$workerKey]['purchased_total'] += $row['subtotal'];
          $grouped[$workerKey]['areas'][$areaKey]['purchased_total'] += $row['subtotal'];
        }
      }
    }

    $this->view('admin/requirements', compact(
      'msg',
      'week',
      'grouped',
      'selectedWeekStart',
      'weekOptions',
      'mailLogs',
      'workers',
      'supplies',
      'unitMeasures',
      'defaultRequirementDate',
      'weeklyEstimatedTotal',
      'weeklyPurchasedTotal',
      'allWeeks', 'purchaseStatus',
      'weeklyUnpricedItems'
    ));
  }

  public function purchaseExpenses(): void
  {
    Auth::requireRole('admin');

    $currentYear = (int)date('Y');
    $selectedYear = filter_var(
      $_GET['year'] ?? $currentYear,
      FILTER_VALIDATE_INT,
      ['options' => ['min_range' => 2000, 'max_range' => 2100]]
    );
    $selectedYear = $selectedYear !== false ? (int)$selectedYear : $currentYear;

    $years = Requirement::purchaseExpenseYears();
    if (!in_array($currentYear, $years, true)) {
      $years[] = $currentYear;
    }
    if (!in_array($selectedYear, $years, true)) {
      $years[] = $selectedYear;
    }
    rsort($years, SORT_NUMERIC);

    $rows = Requirement::purchaseExpensesByYear($selectedYear);
    $months = array_fill(1, 12, [
      'products' => [],
      'request_count' => 0,
      'total' => 0.0,
      'unpriced_count' => 0,
    ]);

    foreach ($rows as $row) {
      $month = (int)$row['month_number'];
      if ($month < 1 || $month > 12) {
        continue;
      }

      $row['request_count'] = (int)$row['request_count'];
      $row['total_quantity'] = (float)$row['total_quantity'];
      $row['subtotal'] = round((float)$row['subtotal'], 2);
      $row['unpriced_count'] = (int)$row['unpriced_count'];
      $months[$month]['products'][] = $row;
      $months[$month]['request_count'] += $row['request_count'];
      $months[$month]['total'] += $row['subtotal'];
      $months[$month]['unpriced_count'] += $row['unpriced_count'];
    }

    foreach ($months as &$month) {
      $month['top_products'] = array_slice($month['products'], 0, 10);
      unset($month['products']);
      $month['total'] = round($month['total'], 2);
    }
    unset($month);

    $annualTotal = round(array_sum(array_column($months, 'total')), 2);
    $annualRequests = array_sum(array_column($months, 'request_count'));
    $monthsWithExpenses = count(array_filter(
      $months,
      static fn(array $month): bool => $month['request_count'] > 0
    ));

    $this->view('admin/purchase_expenses', compact(
      'selectedYear',
      'years',
      'months',
      'annualTotal',
      'annualRequests',
      'monthsWithExpenses'
    ));
  }

  public function purchaseFrequency(): void
  {
    Auth::requireRole('admin');

    $today = new DateTimeImmutable('today');
    $defaultFrom = $today->modify('-6 months')->format('Y-m-d');
    $dateFrom = $this->validDate((string)($_GET['date_from'] ?? '')) ?? $defaultFrom;
    $dateTo = $this->validDate((string)($_GET['date_to'] ?? '')) ?? $today->format('Y-m-d');
    if ($dateFrom > $dateTo) {
      [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
    }

    $purchaseAreaId = max(0, (int)($_GET['purchase_area_id'] ?? 0));
    $productSearch = trim((string)($_GET['product'] ?? ''));
    $frequencyFilter = trim((string)($_GET['frequency'] ?? ''));
    $statusFilter = trim((string)($_GET['status'] ?? ''));
    $allowedFrequencies = ['weekly', 'biweekly', 'monthly', 'sporadic', 'irregular', 'insufficient'];
    $allowedStatuses = ['overdue', 'today', 'soon', 'normal', 'irregular', 'insufficient'];
    if (!in_array($frequencyFilter, $allowedFrequencies, true)) {
      $frequencyFilter = '';
    }
    if (!in_array($statusFilter, $allowedStatuses, true)) {
      $statusFilter = '';
    }

    $rows = Requirement::purchasedItemsBetween($dateFrom, $dateTo, $purchaseAreaId, $productSearch);
    $products = [];
    $calendarEvents = [];
    $totalSpend = 0.0;

    foreach ($rows as $row) {
      $key = (string)$row['product_key'];
      $date = date('Y-m-d', strtotime($row['purchased_at']));
      $subtotal = $row['subtotal'] !== null ? (float)$row['subtotal'] : null;
      if (!isset($products[$key])) {
        $products[$key] = [
          'product_key' => $key,
          'product_name' => $row['product_name'],
          'purchase_dates' => [],
          'purchases' => [],
          'purchase_count' => 0,
          'total_quantity' => 0.0,
          'total_spend' => 0.0,
          'unpriced_count' => 0,
        ];
      }

      $products[$key]['purchase_dates'][$date] = true;
      $products[$key]['purchase_count']++;
      $products[$key]['total_quantity'] += (float)($row['quantity'] ?? 0);
      if ($subtotal !== null) {
        $products[$key]['total_spend'] += $subtotal;
        $totalSpend += $subtotal;
      } else {
        $products[$key]['unpriced_count']++;
      }
      $products[$key]['purchases'][] = $row;
      $calendarEvents[$date][] = $row;
    }

    foreach ($products as &$product) {
      $dates = array_keys($product['purchase_dates']);
      sort($dates);
      $intervals = [];
      for ($index = 1, $count = count($dates); $index < $count; $index++) {
        $previous = new DateTimeImmutable($dates[$index - 1]);
        $current = new DateTimeImmutable($dates[$index]);
        $intervals[] = (int)$previous->diff($current)->days;
      }

      $product['dates'] = $dates;
      $product['intervals'] = $intervals;
      $product['last_purchase'] = !empty($dates) ? end($dates) : null;
      $product['median_days'] = count($dates) >= 3 ? $this->median($intervals) : null;
      $product['is_irregular'] = false;
      $product['next_purchase'] = null;
      $product['days_until_next'] = null;

      if ($product['median_days'] !== null) {
        $spread = max($intervals) - min($intervals);
        $product['is_irregular'] = $spread > max(3, $product['median_days'] * 0.5);
        $product['next_purchase'] = (new DateTimeImmutable($product['last_purchase']))
          ->modify('+' . max(1, (int)round($product['median_days'])) . ' days')
          ->format('Y-m-d');
        $product['days_until_next'] = (int)$today->diff(
          new DateTimeImmutable($product['next_purchase'])
        )->format('%r%a');
      }

      if (count($dates) < 3) {
        $product['frequency_key'] = 'insufficient';
        $product['frequency_text'] = 'Datos insuficientes';
        $product['status_key'] = 'insufficient';
        $product['status_text'] = 'Datos insuficientes';
        $product['status_class'] = 'secondary';
      } elseif ($product['is_irregular']) {
        $product['frequency_key'] = 'irregular';
        $product['frequency_text'] = 'Irregular';
        $product['status_key'] = 'irregular';
        $product['status_text'] = 'Compra irregular';
        $product['status_class'] = 'warning';
      } else {
        $days = (float)$product['median_days'];
        if ($days <= 10) {
          $product['frequency_key'] = 'weekly';
          $product['frequency_text'] = 'Cada ' . $this->frequencyDaysText($days);
        } elseif ($days <= 20) {
          $product['frequency_key'] = 'biweekly';
          $product['frequency_text'] = 'Cada ' . $this->frequencyDaysText($days);
        } elseif ($days <= 45) {
          $product['frequency_key'] = 'monthly';
          $product['frequency_text'] = 'Cada ' . $this->frequencyDaysText($days);
        } else {
          $product['frequency_key'] = 'sporadic';
          $product['frequency_text'] = 'Cada ' . $this->frequencyDaysText($days);
        }

        $daysUntil = (int)$product['days_until_next'];
        if ($daysUntil < 0) {
          $product['status_key'] = 'overdue';
          $product['status_text'] = 'Vencido por ' . abs($daysUntil) . ' día(s)';
          $product['status_class'] = 'danger';
        } elseif ($daysUntil === 0) {
          $product['status_key'] = 'today';
          $product['status_text'] = 'Comprar hoy';
          $product['status_class'] = 'danger';
        } elseif ($daysUntil <= 7) {
          $product['status_key'] = 'soon';
          $product['status_text'] = 'Próximo en ' . $daysUntil . ' día(s)';
          $product['status_class'] = 'warning';
        } else {
          $product['status_key'] = 'normal';
          $product['status_text'] = 'Dentro del ciclo';
          $product['status_class'] = 'success';
        }
      }
      unset($product['purchase_dates']);
      $product['total_spend'] = round($product['total_spend'], 2);
    }
    unset($product);

    $products = array_values(array_filter($products, static function (array $product) use ($frequencyFilter, $statusFilter): bool {
      return ($frequencyFilter === '' || $product['frequency_key'] === $frequencyFilter)
        && ($statusFilter === '' || $product['status_key'] === $statusFilter);
    }));
    usort($products, static function (array $left, array $right): int {
      $leftDays = $left['days_until_next'] ?? PHP_INT_MAX;
      $rightDays = $right['days_until_next'] ?? PHP_INT_MAX;
      return $leftDays <=> $rightDays ?: strcasecmp($left['product_name'], $right['product_name']);
    });

    $requestedCalendarMonth = (string)($_GET['calendar_month'] ?? '');
    $parsedCalendarMonth = DateTimeImmutable::createFromFormat('!Y-m', $requestedCalendarMonth);
    $calendarMonth = $parsedCalendarMonth && $parsedCalendarMonth->format('Y-m') === $requestedCalendarMonth
      ? $requestedCalendarMonth
      : date('Y-m', strtotime($dateTo));
    $calendarStart = new DateTimeImmutable($calendarMonth . '-01');
    $calendarPrevious = $calendarStart->modify('-1 month')->format('Y-m');
    $calendarNext = $calendarStart->modify('+1 month')->format('Y-m');
    $areas = PurchaseArea::active();
    $summary = [
      'products' => count($products),
      'purchases' => count($rows),
      'spend' => round($totalSpend, 2),
      'due' => count(array_filter($products, static fn(array $product): bool =>
        in_array($product['status_key'], ['overdue', 'today', 'soon'], true)
      )),
    ];

    $this->view('admin/purchase_frequency', compact(
      'dateFrom', 'dateTo', 'purchaseAreaId', 'productSearch', 'frequencyFilter',
      'statusFilter', 'products', 'calendarEvents', 'calendarStart',
      'calendarPrevious', 'calendarNext', 'areas', 'summary'
    ));
  }

  private function validDate(string $date): ?string
  {
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $parsed && $parsed->format('Y-m-d') === $date ? $date : null;
  }

  private function median(array $values): float
  {
    sort($values, SORT_NUMERIC);
    $count = count($values);
    $middle = intdiv($count, 2);
    return $count % 2 === 1
      ? (float)$values[$middle]
      : ((float)$values[$middle - 1] + (float)$values[$middle]) / 2;
  }

  private function frequencyDaysText(float $days): string
  {
    return rtrim(rtrim(number_format($days, 1, '.', ''), '0'), '.') . ' día(s)';
  }

  private function requirementMailLogs(): array
  {
    MailNotificationLog::ensureSchema();
    $pdo = Database::conn();
    return $pdo->query("
      SELECT *
      FROM mail_notification_logs
      WHERE notification_type='requirement_created'
      ORDER BY created_at DESC, id DESC
      LIMIT 20
    ")->fetchAll();
  }

  public function incomeExpenses(): void
  {
    Auth::requireRole('admin');

    $currentYear = (int)date('Y');
    $selectedYear = filter_var(
      $_GET['year'] ?? $currentYear,
      FILTER_VALIDATE_INT,
      ['options' => ['min_range' => 2000, 'max_range' => 2100]]
    );
    $selectedYear = $selectedYear !== false ? (int)$selectedYear : $currentYear;

    $years = FinanceSummary::availableYears();
    foreach ([$currentYear, $selectedYear] as $year) {
      if (!in_array($year, $years, true)) {
        $years[] = $year;
      }
    }
    rsort($years, SORT_NUMERIC);

    $months = FinanceSummary::monthlyForYear($selectedYear);
    $annual = [
      'personnel' => round(array_sum(array_column($months, 'personnel')), 2),
      'purchases' => round(array_sum(array_column($months, 'purchases')), 2),
      'other_expenses' => round(array_sum(array_column($months, 'other_expenses')), 2),
      'sales' => round(array_sum(array_column($months, 'sales')), 2),
    ];
    $annual['expenses'] = round($annual['personnel'] + $annual['purchases'] + $annual['other_expenses'], 2);
    $annual['balance'] = round($annual['sales'] - $annual['expenses'], 2);

    $this->view('admin/income_expenses', compact('selectedYear', 'years', 'months', 'annual'));
  }

  public function otherExpenses(): void
  {
    Auth::requireRole('admin');
    OtherExpense::ensureSchema();
    $msg = null;
    $selectedYear = max(2000, min(2100, (int)($_GET['year'] ?? date('Y'))));
    $selectedMonth = max(1, min(12, (int)($_GET['month'] ?? date('n'))));

    if (Helpers::isPost()) {
      Csrf::check();
      $action = (string)($_POST['action'] ?? '');
      try {
        if ($action === 'create_detail') {
          OtherExpense::createDetail((string)($_POST['name'] ?? ''));
          $msg = ['type' => 'success', 'text' => 'Detalle de gasto creado correctamente.'];
        }
        if ($action === 'toggle_detail') {
          OtherExpense::setDetailActive(
            (int)($_POST['id'] ?? 0),
            (int)($_POST['is_active'] ?? 0)
          );
          $msg = ['type' => 'success', 'text' => 'Estado del detalle actualizado.'];
        }
        if ($action === 'update_detail') {
          OtherExpense::updateDetail(
            (int)($_POST['id'] ?? 0),
            (string)($_POST['name'] ?? '')
          );
          $msg = ['type' => 'success', 'text' => 'Nombre del detalle actualizado correctamente.'];
        }
        if ($action === 'save_month') {
          $period = DateTimeImmutable::createFromFormat('!Y-m', (string)($_POST['period'] ?? ''));
          if (!$period || $period->format('Y-m') !== (string)($_POST['period'] ?? '')) {
            throw new RuntimeException('El periodo seleccionado no es válido.');
          }
          $selectedYear = (int)$period->format('Y');
          $selectedMonth = (int)$period->format('n');
          OtherExpense::saveMonth($period->format('Y-m-01'), (array)($_POST['amounts'] ?? []));
          $msg = ['type' => 'success', 'text' => 'Gastos del mes guardados correctamente.'];
        }
      } catch (Throwable $e) {
        $message = str_contains($e->getMessage(), 'Duplicate entry')
          ? 'Ya existe un detalle de gasto con ese nombre.'
          : $e->getMessage();
        $msg = ['type' => 'danger', 'text' => 'Error: ' . $message];
      }
    }

    $periodMonth = sprintf('%04d-%02d-01', $selectedYear, $selectedMonth);
    $details = OtherExpense::details();
    $monthEntries = OtherExpense::entriesForMonth($periodMonth);
    $monthTotal = round(array_sum(array_map(
      static fn(array $entry): float => (float)$entry['amount'],
      $monthEntries
    )), 2);

    $this->view('admin/other_expenses', compact(
      'msg', 'selectedYear', 'selectedMonth', 'periodMonth',
      'details', 'monthEntries', 'monthTotal'
    ));
  }

  public function otherExpenseStatistics(): void
  {
    Auth::requireRole('admin');
    $currentYear = (int)date('Y');
    $selectedYear = filter_var(
      $_GET['year'] ?? $currentYear,
      FILTER_VALIDATE_INT,
      ['options' => ['min_range' => 2000, 'max_range' => 2100]]
    );
    $selectedYear = $selectedYear !== false ? (int)$selectedYear : $currentYear;

    $years = OtherExpense::availableYears();
    foreach ([$currentYear, $selectedYear] as $year) {
      if (!in_array($year, $years, true)) {
        $years[] = $year;
      }
    }
    rsort($years, SORT_NUMERIC);

    $monthlyTotals = array_fill(1, 12, 0.0);
    foreach (OtherExpense::monthlyTotalsForYear($selectedYear) as $row) {
      $monthlyTotals[(int)$row['month_number']] = round((float)$row['total'], 2);
    }

    $details = [];
    foreach (OtherExpense::detailMonthlyForYear($selectedYear) as $row) {
      $id = (int)$row['expense_detail_id'];
      if (!isset($details[$id])) {
        $details[$id] = [
          'id' => $id,
          'name' => $row['name'],
          'months' => array_fill(1, 12, 0.0),
          'total' => 0.0,
        ];
      }
      $amount = round((float)$row['amount'], 2);
      $details[$id]['months'][(int)$row['month_number']] = $amount;
      $details[$id]['total'] += $amount;
    }
    $details = array_values($details);
    usort($details, static fn(array $left, array $right): int => $right['total'] <=> $left['total']);

    $annualTotal = round(array_sum($monthlyTotals), 2);
    $monthsWithData = count(array_filter($monthlyTotals, static fn(float $amount): bool => $amount > 0));
    $highestMonthAmount = max($monthlyTotals);
    $highestMonth = $highestMonthAmount > 0 ? array_search($highestMonthAmount, $monthlyTotals, true) : null;

    $this->view('admin/other_expense_statistics', compact(
      'selectedYear', 'years', 'monthlyTotals', 'details',
      'annualTotal', 'monthsWithData', 'highestMonth', 'highestMonthAmount'
    ));
  }

  public function activities(): void
  {
    Auth::requireRole('admin');
    $msg = null;

    if (Helpers::isPost()) {
      Csrf::check();
      $action = $_POST['action'] ?? '';

      try {
        if ($action === 'create') {
          Activity::createAssignment(
            (int)($_POST['user_id'] ?? 0),
            trim((string)($_POST['name'] ?? '')),
            isset($_POST['is_active']) ? 1 : 0
          );
          $msg = ['type' => 'success', 'text' => 'Actividad creada'];
        }

        if ($action === 'update') {
          Activity::updateAssignment(
            (int)($_POST['id'] ?? 0),
            (int)($_POST['user_id'] ?? 0),
            trim((string)($_POST['name'] ?? '')),
            isset($_POST['is_active']) ? 1 : 0
          );
          $msg = ['type' => 'success', 'text' => 'Actividad actualizada'];
        }

        if ($action === 'delete') {
          Activity::deleteAssignment((int)($_POST['id'] ?? 0));
          $msg = ['type' => 'warning', 'text' => 'Actividad eliminada'];
        }
      } catch (Throwable $e) {
        $msg = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
      }
    }

    $workers = User::activeWorkers();
    $assignments = Activity::assignedAll();
    $week = Activity::weekRangeForDate();
    $rows = Activity::performedForAdminWeek($week['from']);
    $board = [];

    foreach ($rows as $row) {
      $workerKey = (int)$row['user_id'];
      if (!isset($board[$workerKey])) {
        $board[$workerKey] = [
          'worker_name' => trim($row['first_name'] . ' ' . $row['last_name']),
          'days' => []
        ];
      }

      $board[$workerKey]['days'][$row['activity_date']][] = $row['activity_name'];
    }

    $this->view('admin/activities', compact('msg', 'workers', 'assignments', 'week', 'board'));
  }

  public function tasks(): void
  {
    Auth::requireRole('admin');
    $msg = null;

    if (Helpers::isPost()) {
      Csrf::check();
      $action = $_POST['action'] ?? '';

      try {
        if ($action === 'create_task') {
          Task::createTask(trim((string)($_POST['name'] ?? '')), isset($_POST['is_active']) ? 1 : 0);
          $msg = ['type' => 'success', 'text' => 'Tarea creada'];
        }

        if ($action === 'update_task') {
          Task::updateTask(
            (int)($_POST['id'] ?? 0),
            trim((string)($_POST['name'] ?? '')),
            isset($_POST['is_active']) ? 1 : 0
          );
          $msg = ['type' => 'success', 'text' => 'Tarea actualizada'];
        }

        if ($action === 'delete_task') {
          Task::deleteTask((int)($_POST['id'] ?? 0));
          $msg = ['type' => 'warning', 'text' => 'Tarea eliminada'];
        }

        if ($action === 'create_assignment') {
          Task::createAssignment(
            (int)($_POST['user_id'] ?? 0),
            (int)($_POST['task_id'] ?? 0),
            (int)($_POST['weekday'] ?? 0),
            isset($_POST['is_active']) ? 1 : 0
          );
          $msg = ['type' => 'success', 'text' => 'Asignacion creada'];
        }

        if ($action === 'update_assignment') {
          Task::updateAssignment(
            (int)($_POST['id'] ?? 0),
            (int)($_POST['user_id'] ?? 0),
            (int)($_POST['task_id'] ?? 0),
            (int)($_POST['weekday'] ?? 0),
            isset($_POST['is_active']) ? 1 : 0
          );
          $msg = ['type' => 'success', 'text' => 'Asignacion actualizada'];
        }

        if ($action === 'delete_assignment') {
          Task::deleteAssignment((int)($_POST['id'] ?? 0));
          $msg = ['type' => 'warning', 'text' => 'Asignacion eliminada'];
        }
      } catch (Throwable $e) {
        $msg = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
      }
    }

    $workers = User::activeWorkers();
    $tasks = Task::catalogAll();
    $assignments = Task::assignmentsAll();
    $board = Task::weeklyBoard();

    $this->view('admin/tasks', compact('msg', 'workers', 'tasks', 'assignments', 'board'));
  }


  public function promotions(): void
  {
    Auth::requireRole('admin');
    $msg = null;

    if (Helpers::isPost()) {
      Csrf::check();
      $action = $_POST['action'] ?? '';

      try {
        if ($action === 'create') {
          Promotion::create(
            (int)($_POST['weekday'] ?? 0),
            (string)($_POST['shift'] ?? 'morning'),
            trim((string)($_POST['title'] ?? '')),
            trim((string)($_POST['content'] ?? '')),
            isset($_POST['is_active']) ? 1 : 0
          );
          $msg = ['type' => 'success', 'text' => 'Promoción creada'];
        }

        if ($action === 'update') {
          Promotion::update(
            (int)($_POST['id'] ?? 0),
            (int)($_POST['weekday'] ?? 0),
            (string)($_POST['shift'] ?? 'morning'),
            trim((string)($_POST['title'] ?? '')),
            trim((string)($_POST['content'] ?? '')),
            isset($_POST['is_active']) ? 1 : 0
          );
          $msg = ['type' => 'success', 'text' => 'Promoción actualizada'];
        }

        if ($action === 'delete') {
          Promotion::delete((int)($_POST['id'] ?? 0));
          $msg = ['type' => 'warning', 'text' => 'Promoción eliminada'];
        }
      } catch (Throwable $e) {
        $msg = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
      }
    }

    $promos = Promotion::all();
    $this->view('admin/promotions', compact('promos', 'msg'));
  }

  public function attendance(): void
  {
    Auth::requireRole('admin');
    $msg = null;

    if (Helpers::isPost()) {
      Csrf::check();
      $action = $_POST['action'] ?? '';

      try {
        $userId = (int)($_POST['user_id'] ?? 0);
        $type = (string)($_POST['mark_type'] ?? '');
        $markedAtRaw = trim((string)($_POST['marked_at'] ?? ''));
        $markedAt = DateTime::createFromFormat('!Y-m-d\TH:i', $markedAtRaw);
        if (!$markedAt) {
          $markedAt = DateTime::createFromFormat('Y-m-d H:i:s', $markedAtRaw);
        }

        if ($action !== 'delete') {
          $user = User::find($userId);
          if (!$user || ($user['role'] ?? '') !== 'worker') {
            throw new RuntimeException('Trabajador no válido');
          }
          if (!in_array($type, ['in', 'out'], true)) {
            throw new RuntimeException('Tipo de marcación inválido');
          }
          if (!$markedAt) {
            throw new RuntimeException('Fecha y hora inválidas');
          }
        }

        if ($action === 'create') {
          $late = $this->calculateMinutesLate($userId, $type, $markedAt);
          $ip = trim((string)($_POST['ip_address'] ?? ''));
          $lat = isset($_POST['latitude']) && $_POST['latitude'] !== '' ? (float)$_POST['latitude'] : null;
          $lng = isset($_POST['longitude']) && $_POST['longitude'] !== '' ? (float)$_POST['longitude'] : null;
          $ua = trim((string)($_POST['user_agent'] ?? ''));

          Attendance::create(
            $userId,
            $type,
            $markedAt->format('Y-m-d H:i:s'),
            $late,
            $ip !== '' ? $ip : null,
            $lat,
            $lng,
            $ua !== '' ? $ua : null
          );
          $msg = ['type' => 'success', 'text' => 'Asistencia registrada'];
        }

        if ($action === 'update') {
          $id = (int)($_POST['id'] ?? 0);
          $late = $this->calculateMinutesLate($userId, $type, $markedAt);
          $ip = trim((string)($_POST['ip_address'] ?? ''));
          $lat = isset($_POST['latitude']) && $_POST['latitude'] !== '' ? (float)$_POST['latitude'] : null;
          $lng = isset($_POST['longitude']) && $_POST['longitude'] !== '' ? (float)$_POST['longitude'] : null;
          $ua = trim((string)($_POST['user_agent'] ?? ''));

          Attendance::update(
            $id,
            $userId,
            $type,
            $markedAt->format('Y-m-d H:i:s'),
            $late,
            $ip !== '' ? $ip : null,
            $lat,
            $lng,
            $ua !== '' ? $ua : null,
            (int)(Auth::user()['id'] ?? 0)
          );
          $msg = ['type' => 'success', 'text' => 'Asistencia actualizada'];
        }

        if ($action === 'delete') {
          Attendance::delete((int)($_POST['id'] ?? 0));
          $msg = ['type' => 'warning', 'text' => 'Asistencia eliminada'];
        }
      } catch (Throwable $e) {
        $msg = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
      }
    }

    $doc = trim($_GET['doc'] ?? '');
    $from = trim($_GET['from'] ?? '');
    $to = trim($_GET['to'] ?? '');

    $rows = Attendance::filter($doc ?: null, $from ?: null, $to ?: null);
    $workers = User::activeWorkers();
    $this->view('admin/attendance', compact('rows', 'doc', 'from', 'to', 'workers', 'msg'));
  }

  public function payroll(): void
  {
    Auth::requireRole('admin');
    Payroll::ensureSchema();

    $msg = null;

    if (Helpers::isPost()) {
      Csrf::check();
      $action = $_POST['action'] ?? '';

      try {
        if ($action === 'create') {
          $id = Payroll::create($_POST, (int)(Auth::user()['id'] ?? 0));
          $msg = ['type' => 'success', 'text' => 'Pago generado #' . $id];
        }

        if ($action === 'update') {
          $id = (int)($_POST['id'] ?? 0);
          Payroll::update($id, $_POST);
          $msg = ['type' => 'success', 'text' => 'Pago actualizado #' . $id];
        }

        if ($action === 'delete') {
          Payroll::delete((int)($_POST['id'] ?? 0));
          $msg = ['type' => 'warning', 'text' => 'Pago eliminado'];
        }
      } catch (Throwable $e) {
        $msg = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
      }
    }

    $workers = User::activeWorkers();
    $selectedMonth = trim((string)($_GET['month'] ?? date('Y-m')));
    if (!DateTime::createFromFormat('Y-m', $selectedMonth)) {
      $selectedMonth = date('Y-m');
    }

    $preview = null;
    $editingPayroll = null;
    $editId = (int)($_GET['edit_id'] ?? 0);
    if ($editId > 0) {
      try {
        $editingPayroll = Payroll::find($editId);
        if (!$editingPayroll) {
          throw new RuntimeException('El pago que intentas editar no existe.');
        }

        $preview = Payroll::calculatePreview([
          'user_id' => (int)$editingPayroll['user_id'],
          'payment_type' => $editingPayroll['payment_type'],
          'period_month' => date('Y-m', strtotime($editingPayroll['period_month'])),
          'period_part' => date('j', strtotime($editingPayroll['period_start'])) >= 16 ? 'second' : 'first',
          'week_start' => $editingPayroll['period_start'],
          'salary_basis' => $editingPayroll['salary_basis'],
          'salary_amount' => $editingPayroll['salary_amount'],
          'base_days' => $editingPayroll['base_days'],
          'hours_per_day' => $editingPayroll['hours_per_day'],
          'worked_days' => $editingPayroll['worked_days'],
          'late_minutes' => $editingPayroll['late_minutes'],
          'is_published' => $editingPayroll['is_published'] ?? 0,
          'items' => [
            'type' => array_column($editingPayroll['items'], 'item_type'),
            'concept' => array_column($editingPayroll['items'], 'concept'),
            'amount' => array_column($editingPayroll['items'], 'amount'),
          ],
        ]);
      } catch (Throwable $e) {
        $msg = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
      }
    } elseif ((int)($_GET['user_id'] ?? 0) > 0) {
      try {
        $preview = Payroll::calculatePreview([
          'user_id' => (int)($_GET['user_id'] ?? 0),
          'payment_type' => $_GET['payment_type'] ?? 'biweekly',
          'period_month' => $selectedMonth,
          'period_part' => $_GET['period_part'] ?? 'first',
          'week_start' => $_GET['week_start'] ?? '',
          'salary_basis' => $_GET['salary_basis'] ?? '',
          'salary_amount' => $_GET['salary_amount'] ?? '',
          'base_days' => $_GET['base_days'] ?? '',
          'hours_per_day' => $_GET['hours_per_day'] ?? '',
        ]);
      } catch (Throwable $e) {
        $msg = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
      }
    }

    $rows = Payroll::recent($selectedMonth);
    $this->view('admin/payroll', compact('msg', 'workers', 'selectedMonth', 'preview', 'rows', 'editingPayroll'));
  }

  public function inventory(): void
  {
    Auth::requireRole('admin');
    $msg = null;

    $areaId = (int)($_GET['area_id'] ?? 0);
    $status = $_GET['status'] ?? '';

    $statusFilter = null;
    if ($status === 'active') {
      $statusFilter = 1;
    } elseif ($status === 'inactive') {
      $statusFilter = 0;
    }

    if (Helpers::isPost()) {
      Csrf::check();
      $action = $_POST['action'] ?? '';

      $uploadedImage = null;
      $previousImage = null;
      $pdo = Database::conn();
      try {
        $userId = (int)($_POST['user_id'] ?? 0);
        $itemAreaId = (int)($_POST['area_id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $quantityRaw = trim((string)($_POST['quantity'] ?? ''));
        $quantity = ctype_digit($quantityRaw) ? (int)$quantityRaw : -1;
        $unit = trim((string)($_POST['unit'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if (in_array($action, ['create', 'update'], true)) {
          $worker = User::findWithDetails($userId);
          if (!$worker || ($worker['role'] ?? '') !== 'worker' || (int)($worker['is_active'] ?? 0) !== 1) {
            throw new RuntimeException('Debes seleccionar un trabajador activo.');
          }
          if ($itemAreaId <= 0) {
            throw new RuntimeException('Debes seleccionar un area.');
          }
          if ($name === '') {
            throw new RuntimeException('El nombre del item es obligatorio.');
          }
          if ($quantity < 0) {
            throw new RuntimeException('La cantidad debe ser un numero entero.');
          }
          if ($unit === '') {
            throw new RuntimeException('La unidad es obligatoria.');
          }
        }

        InventoryItem::ensureSchema();
        $pdo->beginTransaction();
        if (in_array($action, ['update', 'delete'], true)) {
          $st = $pdo->prepare('SELECT image_name FROM inventory_items WHERE id=? FOR UPDATE');
          $st->execute([(int)($_POST['id'] ?? 0)]);
          $current = $st->fetch();
          if (!$current) throw new RuntimeException('El item de inventario no existe.');
          $previousImage = $current['image_name'];
        }
        if (in_array($action, ['create', 'update'], true)) $uploadedImage = InventoryImage::upload();

        if ($action === 'create') {
          InventoryItem::create(
            $userId,
            $itemAreaId,
            $name,
            $quantity,
            $unit,
            $notes !== '' ? $notes : null,
            (int)(Auth::user()['id'] ?? 0),
            'admin',
            $uploadedImage
          );
          $msg = ['type' => 'success', 'text' => 'Item de inventario registrado'];
        }

        if ($action === 'update') {
          InventoryItem::updateByAdmin(
            (int)($_POST['id'] ?? 0),
            $userId,
            $itemAreaId,
            $name,
            $quantity,
            $unit,
            $notes !== '' ? $notes : null,
            $isActive,
            (int)(Auth::user()['id'] ?? 0),
            'admin',
            $uploadedImage
          );
          $msg = ['type' => 'success', 'text' => 'Item de inventario actualizado'];
        }

        if ($action === 'delete') {
          InventoryItem::deleteByAdmin((int)($_POST['id'] ?? 0), (int)(Auth::user()['id'] ?? 0));
          $msg = ['type' => 'warning', 'text' => 'Item de inventario eliminado'];
        }
        $pdo->commit();
        if ($uploadedImage || $action === 'delete') InventoryImage::remove($previousImage);
      } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
          $pdo->rollBack();
          InventoryImage::remove($uploadedImage);
        }
        $msg = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
      }
    }

    $areas = WorkArea::all();
    $workers = User::activeWorkers();
    $rows = InventoryItem::forAdmin($areaId > 0 ? $areaId : null, $statusFilter);
    $inventoryHistory = InventoryItem::historyForItems(array_column($rows, 'id'));
    $unseenInventoryUpdates = InventoryItem::unseenWorkerUpdateCounts(array_column($rows, 'id'));

    $grouped = [];
    foreach ($rows as $row) {
      $grouped[$row['area_name']][] = $row;
    }

    $this->view('admin/inventory', compact('areas', 'workers', 'rows', 'grouped', 'inventoryHistory', 'unseenInventoryUpdates', 'areaId', 'status', 'msg'));
  }

  public function inventoryImage(): void
  {
    Auth::requireRole('admin');
    $st = Database::conn()->prepare('SELECT image_name FROM inventory_items WHERE id=?');
    $st->execute([(int)($_GET['id'] ?? 0)]);
    $name = $st->fetchColumn();
    if (!$name || !is_file(InventoryImage::path($name))) {
      http_response_code(404);
      exit('Imagen no encontrada.');
    }
    $path = InventoryImage::path($name);
    $mime = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][pathinfo($path, PATHINFO_EXTENSION)];
    header('Content-Type: ' . $mime);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    header('Content-Length: ' . filesize($path));
    readfile($path);
  }
  public function inventoryHistorySeen(): void
  {
    Auth::requireRole('admin');
    Csrf::check();
    InventoryItem::markHistorySeenByAdmin((int)($_POST['item_id'] ?? 0));
    $this->jsonResponse(['ok' => true]);
  }

  public function beverages(): void
  {
    Auth::requireLogin();
    if (!Auth::canManageBeverages()) {
      http_response_code(403);
      exit('403 - Acceso denegado');
    }

    $limitedBeverageAccess = (Auth::user()['role'] ?? '') !== 'admin';
    BeverageControl::ensureSchema();
    $msg = null;

    if (Helpers::isPost()) {
      Csrf::check();
      $action = $_POST['action'] ?? '';

      try {
        if ($limitedBeverageAccess && in_array($action, ['create_product', 'update_product', 'delete_product', 'delete'], true)) {
          throw new RuntimeException('No tienes permiso para realizar esta accion.');
        }

        if ($action === 'create_product') {
          BeverageControl::createProduct($_POST);
          $msg = ['type' => 'success', 'text' => 'Bebida creada'];
        }

        if ($action === 'update_product') {
          BeverageControl::updateProduct((int)($_POST['id'] ?? 0), $_POST);
          $msg = ['type' => 'success', 'text' => 'Bebida actualizada'];
        }

        if ($action === 'delete_product') {
          BeverageControl::deleteProduct((int)($_POST['id'] ?? 0));
          $msg = ['type' => 'warning', 'text' => 'Bebida eliminada'];
        }

        if ($action === 'create') {
          BeverageControl::createEntry($_POST);
          $msg = ['type' => 'success', 'text' => 'Stock registrado'];
        }

        if ($action === 'update') {
          BeverageControl::updateEntry((int)($_POST['id'] ?? 0), $_POST);
          $msg = ['type' => 'success', 'text' => 'Stock actualizado'];
        }

        if ($action === 'delete') {
          BeverageControl::deleteEntry((int)($_POST['id'] ?? 0));
          $msg = ['type' => 'warning', 'text' => 'Stock eliminado'];
        }

        if ($action === 'sale') {
          BeverageControl::registerSale((int)($_POST['id'] ?? 0), $_POST);
          $msg = ['type' => 'success', 'text' => 'Venta registrada'];
        }
      } catch (Throwable $e) {
        $msg = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
      }
    }

    $allProducts = BeverageControl::products(false);
    $products = BeverageControl::products(true);
    $entries = BeverageControl::entries();
    $salesByEntry = BeverageControl::salesByEntryIds(array_column($entries, 'id'));

    $this->view('admin/beverages', compact('allProducts', 'products', 'entries', 'salesByEntry', 'limitedBeverageAccess', 'msg'));
  }

  public function products(): void
  {
    Auth::requireRole('admin');
    Product::ensureSchema();
    MonthlyProductSale::ensureSchema();

    $msg = null;

    if (Helpers::isPost()) {
      Csrf::check();
      $action = $_POST['action'] ?? '';

      try {
        if ($action === 'create_category') {
          ProductCategory::create(trim((string)($_POST['name'] ?? '')));
          $msg = ['type' => 'success', 'text' => 'Categoria creada'];
        }

        if ($action === 'update_category') {
          ProductCategory::update((int)($_POST['id'] ?? 0), trim((string)($_POST['name'] ?? '')));
          $msg = ['type' => 'success', 'text' => 'Categoria actualizada'];
        }

        if ($action === 'delete_category') {
          ProductCategory::delete((int)($_POST['id'] ?? 0));
          $msg = ['type' => 'warning', 'text' => 'Categoria eliminada'];
        }

        if ($action === 'create_product') {
          Product::create([
            'category_id' => (int)($_POST['category_id'] ?? 0),
            'name' => trim((string)($_POST['name'] ?? '')),
            'variant' => trim((string)($_POST['variant'] ?? '')),
            'brand' => trim((string)($_POST['brand'] ?? '')),
            'internal_code' => trim((string)($_POST['internal_code'] ?? '')),
            'manufacturer_code' => trim((string)($_POST['manufacturer_code'] ?? '')),
            'unit_price' => $_POST['unit_price'] ?? null,
            'cost_price' => $_POST['cost_price'] ?? null,
            'stock_quantity' => $_POST['stock_quantity'] ?? null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
          ]);
          $msg = ['type' => 'success', 'text' => 'Producto creado'];
        }

        if ($action === 'update_product') {
          Product::update((int)($_POST['id'] ?? 0), [
            'category_id' => (int)($_POST['category_id'] ?? 0),
            'name' => trim((string)($_POST['name'] ?? '')),
            'variant' => trim((string)($_POST['variant'] ?? '')),
            'brand' => trim((string)($_POST['brand'] ?? '')),
            'internal_code' => trim((string)($_POST['internal_code'] ?? '')),
            'manufacturer_code' => trim((string)($_POST['manufacturer_code'] ?? '')),
            'unit_price' => $_POST['unit_price'] ?? null,
            'cost_price' => $_POST['cost_price'] ?? null,
            'stock_quantity' => $_POST['stock_quantity'] ?? null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
          ]);
          $msg = ['type' => 'success', 'text' => 'Producto actualizado'];
        }

        if ($action === 'update_price') {
          $price = isset($_POST['unit_price']) && $_POST['unit_price'] !== '' ? (float)$_POST['unit_price'] : null;
          Product::updatePrice((int)($_POST['id'] ?? 0), $price);
          $msg = ['type' => 'success', 'text' => 'Precio actualizado'];
        }

        if ($action === 'delete_product') {
          Product::delete((int)($_POST['id'] ?? 0));
          $msg = ['type' => 'warning', 'text' => 'Producto eliminado'];
        }

        if ($action === 'import_inventory') {
          $upload = $this->uploadedXlsxPath('inventory_file');
          $count = $this->importInventoryCatalog($upload);
          $msg = ['type' => 'success', 'text' => 'Catalogo importado: ' . $count . ' producto(s) procesados'];
        }
      } catch (Throwable $e) {
        $msg = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
      }
    }

    $categoryId = (int)($_GET['category_id'] ?? 0);
    $search = trim((string)($_GET['q'] ?? ''));

    $summary = Product::summary();
    $categories = ProductCategory::all();
    $grouped = Product::groupedByCategory($categoryId > 0 ? $categoryId : null, $search);
    $rows = Product::byCategory($categoryId > 0 ? $categoryId : null, $search);

    $this->view('admin/products', compact('msg', 'summary', 'categories', 'grouped', 'rows', 'categoryId', 'search'));
  }

  public function deliveryPricing(): void
  {
    Auth::requireRole('admin');
    $msg = null;
    if (Helpers::isPost()) {
      Csrf::check();
      try {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'update_commissions') {
          DeliveryPricing::updateCommissions((array)($_POST['commissions'] ?? []));
          $msg = ['type'=>'success','text'=>'Comisiones actualizadas. Todos los precios sugeridos fueron recalculados.'];
        }
        if ($action === 'save_product_prices') {
          DeliveryPricing::saveProductPrices((int)($_POST['product_id'] ?? 0),(array)($_POST['prices'] ?? []));
          $msg = ['type'=>'success','text'=>'Precios de delivery guardados.'];
        }
      } catch (Throwable $e) {
        $msg = ['type'=>'danger','text'=>'Error: '.$e->getMessage()];
      }
    }
    $search=trim((string)($_GET['q']??''));
    $categoryId=max(0,(int)($_GET['category_id']??0));
    $platforms=DeliveryPricing::platforms();
    $products=DeliveryPricing::products($search,$categoryId);
    $categories=ProductCategory::all();
    $this->view('admin/delivery_pricing',compact('msg','search','categoryId','platforms','products','categories'));
  }

  public function incentives(): void
  {
    Auth::requireRole('admin');
    $msg=null;
    if(Helpers::isPost()){
      Csrf::check();
      try{
        $action=(string)($_POST['action']??'');
        if($action==='create'){Incentive::create($_POST);$msg=['type'=>'success','text'=>'Incentivo o bono creado.'];}
        if($action==='update'){Incentive::update((int)($_POST['id']??0),$_POST);$msg=['type'=>'success','text'=>'Incentivo o bono actualizado.'];}
        if($action==='delete'){Incentive::delete((int)($_POST['id']??0));$msg=['type'=>'warning','text'=>'Incentivo o bono eliminado.'];}
      }catch(Throwable $e){$msg=['type'=>'danger','text'=>'Error: '.$e->getMessage()];}
    }
    $search=trim((string)($_GET['q']??''));$status=(string)($_GET['status']??'');$publication=(string)($_GET['publication']??'');
    $incentives=Incentive::all($search,$status,$publication);
    $this->view('admin/incentives',compact('msg','search','status','publication','incentives'));
  }

  public function jobFunctions(): void
  {
    Auth::requireRole('admin'); $msg=null;
    if (Helpers::isPost()) {
      Csrf::check();
      try {
        $action=(string)($_POST['action']??'');
        if ($action==='create') { JobFunction::create($_POST); $msg=['type'=>'success','text'=>'Funciones registradas correctamente.']; }
        elseif ($action==='update') { JobFunction::update((int)($_POST['id']??0),$_POST); $msg=['type'=>'success','text'=>'Funciones actualizadas correctamente.']; }
        elseif ($action==='delete') { JobFunction::delete((int)($_POST['id']??0)); $msg=['type'=>'warning','text'=>'Registro eliminado.']; }
      } catch (Throwable $e) { $msg=['type'=>'danger','text'=>'Error: '.$e->getMessage()]; }
    }
    $search=trim((string)($_GET['q']??'')); $areaId=max(0,(int)($_GET['area_id']??0));
    $status=(string)($_GET['status']??''); $publication=(string)($_GET['publication']??'');
    $areas=WorkArea::all(); $functions=JobFunction::all($search,$areaId,$status,$publication);
    $this->view('admin/job_functions',compact('msg','search','areaId','status','publication','areas','functions'));
  }

  public function costing(): void
  {
    Auth::requireRole('admin');
    Costing::ensureSchema();
    Product::ensureSchema();
    $msg = null;

    if (Helpers::isPost()) {
      Csrf::check();
      $action = $_POST['action'] ?? '';

      try {
        if ($action === 'create') {
          $id = Costing::create($_POST);
          $msg = ['type' => 'success', 'text' => 'Costeo registrado #' . $id];
        }

        if ($action === 'update') {
          Costing::update((int)($_POST['id'] ?? 0), $_POST);
          $msg = ['type' => 'success', 'text' => 'Costeo actualizado'];
        }

        if ($action === 'delete') {
          Costing::delete((int)($_POST['id'] ?? 0));
          $msg = ['type' => 'warning', 'text' => 'Costeo eliminado'];
        }
      } catch (Throwable $e) {
        $msg = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
      }
    }

    $search = trim((string)($_GET['q'] ?? ''));
    $products = Product::activeList();
    $recipes = Recipe::allForAdmin(null, 'approved');
    $supplies = Supply::activeForRequirements();
    $costings = Costing::all($search);
    $this->view('admin/costing', compact('msg', 'products', 'recipes', 'supplies', 'costings', 'search'));
  }

  public function recipes(): void
  {
    Auth::requireRole('admin');
    $msg = null;

    if (Helpers::isPost()) {
      Csrf::check();
      $action = $_POST['action'] ?? '';

      try {
        if ($action === 'update') {
          Recipe::updateByAdmin(
            (int)($_POST['id'] ?? 0),
            (string)($_POST['area_type'] ?? ''),
            (string)($_POST['title'] ?? ''),
            (array)($_POST['ingredients'] ?? []),
            (string)($_POST['preparation'] ?? ''),
            isset($_POST['approved']) ? 'approved' : 'pending'
          );
          $msg = ['type' => 'success', 'text' => 'Receta actualizada'];
        }

        if ($action === 'delete') {
          Recipe::deleteByAdmin((int)($_POST['id'] ?? 0));
          $msg = ['type' => 'warning', 'text' => 'Receta eliminada'];
        }
      } catch (Throwable $e) {
        $msg = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
      }
    }

    $areaType = $_GET['area_type'] ?? '';
    $status = $_GET['status'] ?? '';
    $recipes = Recipe::allForAdmin($areaType, $status);
    $this->view('admin/recipes', compact('msg', 'recipes', 'areaType', 'status'));
  }

  public function sales(): void
  {
    Auth::requireRole('admin');
    MonthlyProductSale::ensureSchema();

    $msg = null;

    if (Helpers::isPost()) {
      Csrf::check();
      $action = $_POST['action'] ?? '';

      try {
        if ($action === 'import_sales') {
          $upload = $this->uploadedXlsxPath('sales_file');
          $result = $this->importMonthlySales($upload, $_POST['sales_month'] ?? null);
          $msg = [
            'type' => 'success',
            'text' => 'Ventas importadas: ' . $result['count'] . ' producto(s) para ' . date('m/Y', strtotime($result['period_month'])) .
              '. Monto origen: S/ ' . number_format((float)$result['raw_total_amount'], 2) .
              '. Monto normalizado: S/ ' . number_format((float)$result['normalized_total_amount'], 2) .
              '. Incidencias auditadas: ' . (int)$result['issues_count']
          ];
        }
      } catch (Throwable $e) {
        $msg = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
      }
    }

    $months = MonthlyProductSale::availableMonths();
    $selectedMonth = trim((string)($_GET['month'] ?? ''));
    if ($selectedMonth === '' && !empty($months)) {
      $selectedMonth = date('Y-m', strtotime($months[0]['period_month']));
    }

    $periodMonth = null;
    if ($selectedMonth !== '') {
      $date = DateTime::createFromFormat('Y-m', $selectedMonth);
      if ($date) {
        $periodMonth = $date->format('Y-m-01');
      }
    }

    $categoryId = (int)($_GET['category_id'] ?? 0);
    $categories = ProductCategory::all();
    $overview = $periodMonth ? MonthlyProductSale::overview($periodMonth, $categoryId > 0 ? $categoryId : null) : [];
    $topProducts = $periodMonth ? MonthlyProductSale::topProducts($periodMonth, null, 20) : [];
    $topByCategory = $periodMonth ? MonthlyProductSale::topProducts($periodMonth, $categoryId > 0 ? $categoryId : null, 20) : [];
    $categoryBreakdown = $periodMonth ? MonthlyProductSale::byCategory($periodMonth) : [];
    $latestAudit = $periodMonth ? SalesImportAudit::latestForMonth($periodMonth) : null;
    $auditIssues = $latestAudit ? SalesImportAudit::issues((int)$latestAudit['id'], 50) : [];
    $recentAudits = SalesImportAudit::recent($periodMonth, 10);

    $this->view('admin/sales', compact(
      'msg',
      'months',
      'categories',
      'selectedMonth',
      'periodMonth',
      'categoryId',
      'overview',
      'topProducts',
      'topByCategory',
      'categoryBreakdown',
      'latestAudit',
      'auditIssues',
      'recentAudits'
    ));
  }

  public function salesStatistics(): void
  {
    Auth::requireRole('admin');
    MonthlyProductSale::ensureSchema();

    $years = MonthlyProductSale::availableYears();
    $selectedYear = (int)($_GET['year'] ?? 0);
    if ($selectedYear <= 0) {
      $selectedYear = !empty($years) ? (int)$years[0]['year'] : (int)date('Y');
    }

    $categoryId = (int)($_GET['category_id'] ?? 0);
    $categories = ProductCategory::all();
    $monthlyTotals = MonthlyProductSale::monthlyTotalsForYear($selectedYear);
    $productMonthlyRows = MonthlyProductSale::productMonthlyForYear(
      $selectedYear,
      $categoryId > 0 ? $categoryId : null
    );

    $this->view('admin/sales_statistics', compact(
      'years',
      'selectedYear',
      'categoryId',
      'categories',
      'monthlyTotals',
      'productMonthlyRows'
    ));
  }

  public function leadDinnerStatuses(): void
  {
    Auth::requireRole('admin');
    LeadDinnerStatus::ensureSchema();
    $msg = null;

    if (Helpers::isPost()) {
      Csrf::check();
      $action = $_POST['action'] ?? '';

      try {
        if ($action === 'create') {
          LeadDinnerStatus::create(trim((string)($_POST['name'] ?? '')), isset($_POST['is_active']) ? 1 : 0);
          $msg = ['type' => 'success', 'text' => 'Estado creado'];
        }

        if ($action === 'update') {
          LeadDinnerStatus::update((int)($_POST['id'] ?? 0), trim((string)($_POST['name'] ?? '')), isset($_POST['is_active']) ? 1 : 0);
          $msg = ['type' => 'success', 'text' => 'Estado actualizado'];
        }

        if ($action === 'delete') {
          LeadDinnerStatus::delete((int)($_POST['id'] ?? 0));
          $msg = ['type' => 'warning', 'text' => 'Estado eliminado'];
        }
      } catch (Throwable $e) {
        $msg = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
      }
    }

    $statuses = LeadDinnerStatus::all();
    $this->view('admin/lead_dinner_statuses', compact('statuses', 'msg'));
  }

  public function leadDinnerCampaigns(): void
  {
    Auth::requireRole('admin');
    LeadDinnerEntry::ensureSchema();
    $msg = null;

    if (Helpers::isPost()) {
      Csrf::check();
      $action = $_POST['action'] ?? '';

      try {
        if ($action === 'create') {
          LeadDinnerCampaign::create($_POST);
          $msg = ['type' => 'success', 'text' => 'Campaña creada'];
        }

        if ($action === 'update') {
          LeadDinnerCampaign::update((int)($_POST['id'] ?? 0), $_POST);
          $msg = ['type' => 'success', 'text' => 'Campaña actualizada'];
        }

        if ($action === 'delete') {
          LeadDinnerCampaign::delete((int)($_POST['id'] ?? 0));
          $msg = ['type' => 'warning', 'text' => 'Campaña eliminada'];
        }
      } catch (Throwable $e) {
        $msg = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
      }
    }

    $campaigns = LeadDinnerCampaign::all();
    $this->view('admin/lead_dinner_campaigns', compact('campaigns', 'msg'));
  }

  public function leadDinnerEntries(): void
  {
    Auth::requireLogin();
    if (!Auth::canManageLeadDinner()) {
      http_response_code(403);
      exit('403 - Acceso denegado');
    }

    $limitedLeadAccess = (Auth::user()['role'] ?? '') !== 'admin';
    LeadDinnerEntry::ensureSchema();
    $msg = null;

    if (Helpers::isPost()) {
      Csrf::check();
      $action = $_POST['action'] ?? '';

      try {
        if ($limitedLeadAccess && !in_array($action, ['create', 'update'], true)) {
          throw new RuntimeException('No tienes permiso para realizar esta accion.');
        }

        if ($action === 'create') {
          if ($limitedLeadAccess) {
            $_POST['status_id'] = LeadDinnerStatus::firstActiveId() ?? 0;
          }
          $data = $this->leadDinnerData($_POST);
          $upload = $this->uploadLeadDinnerVoucher('voucher', false);

          try {
            LeadDinnerEntry::create([
              'first_name' => $data['firstName'],
              'last_name' => $data['lastName'],
              'whatsapp' => $data['whatsapp'],
              'email' => $data['email'],
              'voucher_path' => $upload['path'] ?? null,
              'voucher_original_name' => $upload['original_name'] ?? null,
              'status_id' => $data['statusId'],
              'campaign_id' => $data['campaignId'],
            ]);
          } catch (Throwable $e) {
            if ($upload && is_file($upload['absolute_path'])) {
              unlink($upload['absolute_path']);
            }
            throw $e;
          }

          $msg = ['type' => 'success', 'text' => 'Lead creado'];
        }

        if ($action === 'update') {
          $id = (int)($_POST['id'] ?? 0);
          $current = LeadDinnerEntry::find($id);
          if (!$current) {
            throw new RuntimeException('El lead que intentas editar no existe.');
          }

          if ($limitedLeadAccess) {
            $_POST['status_id'] = (int)$current['status_id'];
          }
          $data = $this->leadDinnerData($_POST);
          $upload = $this->uploadLeadDinnerVoucher('voucher', false);
          $voucherPath = $upload['path'] ?? $current['voucher_path'];
          $voucherOriginalName = $upload['original_name'] ?? $current['voucher_original_name'];

          try {
            LeadDinnerEntry::update($id, [
              'first_name' => $data['firstName'],
              'last_name' => $data['lastName'],
              'whatsapp' => $data['whatsapp'],
              'email' => $data['email'],
              'voucher_path' => $voucherPath,
              'voucher_original_name' => $voucherOriginalName,
              'status_id' => $data['statusId'],
              'campaign_id' => $data['campaignId'],
            ]);
          } catch (Throwable $e) {
            if ($upload && is_file($upload['absolute_path'])) {
              unlink($upload['absolute_path']);
            }
            throw $e;
          }

          if ($upload) {
            $this->deleteLeadDinnerVoucher((string)$current['voucher_path']);
          }
          $msg = ['type' => 'success', 'text' => 'Lead actualizado'];
        }

        if ($action === 'delete') {
          $id = (int)($_POST['id'] ?? 0);
          $current = LeadDinnerEntry::find($id);
          if (!$current) {
            throw new RuntimeException('El lead que intentas eliminar no existe.');
          }

          LeadDinnerEntry::delete($id);
          $this->deleteLeadDinnerVoucher((string)$current['voucher_path']);
          $msg = ['type' => 'warning', 'text' => 'Lead eliminado'];
        }

        if ($action === 'update_status') {
          LeadDinnerEntry::updateStatus((int)($_POST['id'] ?? 0), (int)($_POST['status_id'] ?? 0));
          $msg = ['type' => 'success', 'text' => 'Estado del lead actualizado'];
        }
      } catch (Throwable $e) {
        $msg = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
      }
    }

    $statusId = (int)($_GET['status_id'] ?? 0);
    $search = trim((string)($_GET['q'] ?? ''));
    $statuses = LeadDinnerStatus::all();
    $campaigns = LeadDinnerCampaign::active();
    $rows = LeadDinnerEntry::all($statusId > 0 ? $statusId : null, $search);

    $this->view('admin/lead_dinner_entries', compact(
      'rows',
      'statuses',
      'campaigns',
      'statusId',
      'search',
      'msg',
      'limitedLeadAccess'
    ));
  }
}
