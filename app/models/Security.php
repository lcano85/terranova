<?php
require_once __DIR__ . '/../core/Database.php';

class Security
{
  private static bool $schemaEnsured = false;
  private static array $permissionCache = [];

  public static function ensureSchema(): void
  {
    if (self::$schemaEnsured) {
      return;
    }

    $pdo = Database::conn();
    $pdo->exec("
      CREATE TABLE IF NOT EXISTS security_roles (
        id INT NOT NULL AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        slug VARCHAR(100) NOT NULL,
        description VARCHAR(255) NULL,
        is_system TINYINT(1) NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_security_roles_slug (slug)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $pdo->exec("
      CREATE TABLE IF NOT EXISTS security_permissions (
        id INT NOT NULL AUTO_INCREMENT,
        parent_id INT NULL,
        permission_key VARCHAR(120) NOT NULL,
        name VARCHAR(120) NOT NULL,
        route VARCHAR(180) NULL,
        menu_order INT NOT NULL DEFAULT 0,
        menu_context VARCHAR(20) NOT NULL DEFAULT 'all',
        is_menu TINYINT(1) NOT NULL DEFAULT 1,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_security_permissions_key (permission_key),
        KEY idx_security_permissions_parent (parent_id),
        KEY idx_security_permissions_route (route),
        CONSTRAINT fk_security_permissions_parent
          FOREIGN KEY (parent_id) REFERENCES security_permissions (id)
          ON DELETE SET NULL ON UPDATE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $pdo->exec("
      CREATE TABLE IF NOT EXISTS security_role_permissions (
        role_id INT NOT NULL,
        permission_id INT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (role_id, permission_id),
        CONSTRAINT fk_security_role_permissions_role
          FOREIGN KEY (role_id) REFERENCES security_roles (id)
          ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_security_role_permissions_permission
          FOREIGN KEY (permission_id) REFERENCES security_permissions (id)
          ON DELETE CASCADE ON UPDATE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $pdo->exec("
      CREATE TABLE IF NOT EXISTS security_logs (
        id BIGINT NOT NULL AUTO_INCREMENT,
        user_id INT NULL,
        event_type VARCHAR(40) NOT NULL,
        route VARCHAR(180) NULL,
        module_name VARCHAR(120) NULL,
        ip_address VARCHAR(45) NULL,
        user_agent VARCHAR(500) NULL,
        details VARCHAR(500) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_security_logs_user (user_id),
        KEY idx_security_logs_event (event_type),
        KEY idx_security_logs_created (created_at)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $userColumn = $pdo->query("SHOW COLUMNS FROM users LIKE 'security_role_id'")->fetch();
    if (!$userColumn) {
      $pdo->exec("ALTER TABLE users ADD COLUMN security_role_id INT NULL AFTER role");
      $pdo->exec("ALTER TABLE users ADD KEY idx_users_security_role (security_role_id)");
    }

    self::seedCatalog();
    self::$schemaEnsured = true;
  }

  private static function seedCatalog(): void
  {
    $pdo = Database::conn();
    $roles = [
      ['Administrador', 'administrador', 'Acceso total al sistema.', 1],
      ['Trabajador', 'trabajador', 'Acceso base para trabajadores.', 1],
      ['Barra', 'barra', 'Perfil para responsables de barra y módulos especiales.', 0],
    ];
    $roleSt = $pdo->prepare("
      INSERT INTO security_roles (name, slug, description, is_system, is_active)
      VALUES (?,?,?,?,1)
      ON DUPLICATE KEY UPDATE
        name=VALUES(name), description=VALUES(description), is_system=VALUES(is_system),
        is_active=IF(VALUES(is_system)=1, 1, is_active)
    ");
    foreach ($roles as $role) {
      $roleSt->execute($role);
    }

    $catalog = self::catalog();
    $permissionSt = $pdo->prepare("
      INSERT INTO security_permissions
        (parent_id, permission_key, name, route, menu_order, menu_context, is_menu, is_active)
      VALUES (NULL,?,?,?,?,?,?,1)
      ON DUPLICATE KEY UPDATE
        name=VALUES(name), route=VALUES(route), menu_order=VALUES(menu_order),
        menu_context=VALUES(menu_context), is_menu=VALUES(is_menu), is_active=1
    ");
    foreach ($catalog as $item) {
      $permissionSt->execute([
        $item['key'],
        $item['name'],
        $item['route'],
        $item['order'],
        $item['context'],
        $item['is_menu'],
      ]);
    }

    $ids = [];
    foreach ($pdo->query("SELECT id, permission_key FROM security_permissions")->fetchAll() as $row) {
      $ids[$row['permission_key']] = (int)$row['id'];
    }
    $parentSt = $pdo->prepare("UPDATE security_permissions SET parent_id=? WHERE permission_key=?");
    foreach ($catalog as $item) {
      $parentSt->execute([
        $item['parent'] !== null ? ($ids[$item['parent']] ?? null) : null,
        $item['key'],
      ]);
    }

    $roleRows = $pdo->query("SELECT id, slug FROM security_roles WHERE slug IN ('administrador','trabajador','barra')")->fetchAll();
    $roleIds = [];
    foreach ($roleRows as $row) {
      $roleIds[$row['slug']] = (int)$row['id'];
    }

    if (!empty($roleIds['administrador'])) {
      $grant = $pdo->prepare("INSERT IGNORE INTO security_role_permissions (role_id, permission_id) VALUES (?,?)");
      foreach ($ids as $permissionId) {
        $grant->execute([$roleIds['administrador'], $permissionId]);
      }
      $pdo->prepare("UPDATE users SET security_role_id=? WHERE role='admin' AND security_role_id IS NULL")
        ->execute([$roleIds['administrador']]);
    }

    if (!empty($roleIds['trabajador'])) {
      $specialWorkerKeys = ['worker.leads', 'worker.beverages'];
      $workerKeys = array_column(array_filter(
        $catalog,
        static fn($item) => $item['context'] === 'worker' && !in_array($item['key'], $specialWorkerKeys, true)
      ), 'key');
      if (!empty($specialWorkerKeys)) {
        $placeholders = implode(',', array_fill(0, count($specialWorkerKeys), '?'));
        $deleteSpecial = $pdo->prepare("
          DELETE rp
          FROM security_role_permissions rp
          JOIN security_permissions p ON p.id=rp.permission_id
          WHERE rp.role_id=? AND p.permission_key IN ({$placeholders})
        ");
        $deleteSpecial->execute(array_merge([$roleIds['trabajador']], $specialWorkerKeys));
      }
      $rolePermissionCount = $pdo->prepare("SELECT COUNT(*) FROM security_role_permissions WHERE role_id=?");
      $rolePermissionCount->execute([$roleIds['trabajador']]);
      if ((int)$rolePermissionCount->fetchColumn() === 0) {
        $grant = $pdo->prepare("INSERT IGNORE INTO security_role_permissions (role_id, permission_id) VALUES (?,?)");
        foreach ($workerKeys as $key) {
          if (isset($ids[$key])) {
            $grant->execute([$roleIds['trabajador'], $ids[$key]]);
          }
        }
      }
      $pdo->prepare("UPDATE users SET security_role_id=? WHERE role='worker' AND security_role_id IS NULL")
        ->execute([$roleIds['trabajador']]);
    }

    if (!empty($roleIds['barra'])) {
      $barraKeys = array_column(array_filter($catalog, static fn($item) => $item['context'] === 'worker'), 'key');
      $rolePermissionCount = $pdo->prepare("SELECT COUNT(*) FROM security_role_permissions WHERE role_id=?");
      $rolePermissionCount->execute([$roleIds['barra']]);
      if ((int)$rolePermissionCount->fetchColumn() === 0) {
        $grant = $pdo->prepare("INSERT IGNORE INTO security_role_permissions (role_id, permission_id) VALUES (?,?)");
        foreach ($barraKeys as $key) {
          if (isset($ids[$key])) {
            $grant->execute([$roleIds['barra'], $ids[$key]]);
          }
        }
      }
      $pdo->prepare("UPDATE users SET security_role_id=? WHERE document_number=? AND role='worker'")
        ->execute([$roleIds['barra'], '74581146']);
    }
  }

  private static function catalog(): array
  {
    $items = [];
    $add = static function (string $key, string $name, ?string $route, int $order, string $context, ?string $parent = null, int $isMenu = 1) use (&$items): void {
      $items[] = compact('key', 'name', 'route', 'order', 'context', 'parent') + ['is_menu' => $isMenu];
    };

    $add('admin.dashboard', 'Dashboard', '/admin', 10, 'admin');
    $add('admin.personal', 'Personal', null, 20, 'admin');
    $add('admin.workers', 'Trabajadores', '/admin/workers', 21, 'admin', 'admin.personal');
    $add('admin.shifts', 'Turnos', '/admin/shifts', 22, 'admin', 'admin.personal');
    $add('admin.areas', 'Áreas', '/admin/areas', 23, 'admin', 'admin.personal');
    $add('admin.attendance', 'Asistencia', '/admin/attendance', 24, 'admin', 'admin.personal');
    $add('admin.payroll', 'Pagos', '/admin/payroll', 25, 'admin', 'admin.personal');

    $add('admin.purchases', 'Compras', null, 30, 'admin');
    $add('admin.purchase_areas', 'Áreas de compras', '/admin/purchase-areas', 31, 'admin', 'admin.purchases');
    $add('admin.requirements', 'Requerimientos', '/admin/requirements', 32, 'admin', 'admin.purchases');
    $add('admin.purchase_expenses', 'Gastos en compras', '/admin/purchase-expenses', 33, 'admin', 'admin.purchases');
    $add('admin.purchase_frequency', 'Frecuencia de compras', '/admin/purchase-frequency', 34, 'admin', 'admin.purchases');
    $add('admin.supplies', 'Insumos', '/admin/supplies', 35, 'admin', 'admin.purchases');
    $add('admin.unit_measures', 'Unidades de medida', '/admin/unit-measures', 36, 'admin', 'admin.purchases');

    $add('admin.operations', 'Operaciones', null, 40, 'admin');
    $add('admin.activities', 'Actividades', '/admin/activities', 41, 'admin', 'admin.operations');
    $add('admin.tasks', 'Gestión de tareas', '/admin/tasks', 42, 'admin', 'admin.operations');
    $add('admin.inventory', 'Inventario', '/admin/inventory', 43, 'admin', 'admin.operations');
    $add('admin.beverages', 'Control de bebidas', '/admin/beverages', 44, 'admin', 'admin.operations');

    $add('admin.commercial', 'Comercial', null, 50, 'admin');
    $add('admin.products', 'Productos', '/admin/products', 51, 'admin', 'admin.commercial');
    $add('admin.costing', 'Costeo', '/admin/costing', 52, 'admin', 'admin.commercial');
    $add('admin.recipes', 'Recetario', '/admin/recipes', 53, 'admin', 'admin.commercial');
    $add('admin.sales', 'Ventas', '/admin/sales', 54, 'admin', 'admin.commercial');
    $add('admin.sales_statistics', 'Estadísticas de ventas', '/admin/sales/statistics', 55, 'admin', 'admin.commercial');
    $add('admin.promotions', 'Promociones', '/admin/promotions', 56, 'admin', 'admin.commercial');

    $add('admin.finances', 'Finanzas', null, 58, 'admin');
    $add('admin.income_expenses', 'Egresos - ingresos', '/admin/finance/income-expenses', 59, 'admin', 'admin.finances');

    $add('admin.leads', 'Leads Cena', null, 60, 'admin');
    $add('admin.lead_entries', 'Registros', '/admin/leads-cena', 61, 'admin', 'admin.leads');
    $add('admin.lead_campaigns', 'Campañas', '/admin/leads-cena-campaigns', 62, 'admin', 'admin.leads');
    $add('admin.lead_statuses', 'Estados', '/admin/leads-cena-statuses', 63, 'admin', 'admin.leads');

    $add('security', 'Seguridad', null, 70, 'admin');
    $add('security.users', 'Usuarios', '/admin/security/users', 71, 'admin', 'security');
    $add('security.roles', 'Perfiles', '/admin/security/roles', 72, 'admin', 'security');
    $add('security.permissions', 'Permisos', '/admin/security/permissions', 73, 'admin', 'security');
    $add('security.logs', 'Logs de acceso', '/admin/security/logs', 74, 'admin', 'security');
    $add('admin.profile', 'Mi perfil', '/admin/profile', 90, 'admin');

    $add('worker.dashboard', 'Dashboard', '/worker', 110, 'worker');
    $add('worker.self_service', 'Mi gestión', null, 120, 'worker');
    $add('worker.attendance', 'Mi asistencia', '/worker/attendance', 121, 'worker', 'worker.self_service');
    $add('worker.payments', 'Pagos', '/worker/payments', 122, 'worker', 'worker.self_service');
    $add('worker.inventory', 'Inventario', '/worker/inventory', 123, 'worker', 'worker.self_service');
    $add('worker.requirements', 'Requerimientos', '/worker/requirements', 124, 'worker', 'worker.self_service');
    $add('worker.activities', 'Actividades', '/worker/activities', 125, 'worker', 'worker.self_service');
    $add('worker.tasks', 'Tareas', '/worker/tasks', 126, 'worker', 'worker.self_service');
    $add('worker.recipes', 'Recetario', '/worker/recipes', 127, 'worker', 'worker.self_service');
    $add('worker.profile', 'Mi perfil', '/worker/profile', 128, 'worker', 'worker.self_service');
    $add('worker.leads', 'Leads Cena', '/worker/leads-cena', 129, 'worker', 'worker.self_service');
    $add('worker.beverages', 'Control de bebidas', '/worker/beverages', 130, 'worker', 'worker.self_service');

    return $items;
  }

  public static function roles(bool $activeOnly = false): array
  {
    self::ensureSchema();
    $where = $activeOnly ? 'WHERE is_active=1' : '';
    return Database::conn()->query("SELECT * FROM security_roles {$where} ORDER BY name ASC")->fetchAll();
  }

  public static function role(int $id): ?array
  {
    self::ensureSchema();
    $st = Database::conn()->prepare("SELECT * FROM security_roles WHERE id=? LIMIT 1");
    $st->execute([$id]);
    return $st->fetch() ?: null;
  }

  public static function saveRole(int $id, array $data): int
  {
    self::ensureSchema();
    $name = trim((string)($data['name'] ?? ''));
    if ($name === '') {
      throw new RuntimeException('El nombre del perfil es obligatorio.');
    }
    $slug = self::slug($name);
    $description = trim((string)($data['description'] ?? ''));
    $active = isset($data['is_active']) ? 1 : 0;
    $pdo = Database::conn();

    if ($id > 0) {
      $current = self::role($id);
      if (!$current) {
        throw new RuntimeException('El perfil seleccionado no existe.');
      }
      if ((int)$current['is_system'] === 1) {
        $slug = (string)$current['slug'];
      }
      if ($slug === 'administrador') {
        $active = 1;
      }
      $st = $pdo->prepare("UPDATE security_roles SET name=?, slug=?, description=?, is_active=? WHERE id=?");
      $st->execute([$name, $slug, $description !== '' ? $description : null, $active, $id]);
      self::$permissionCache = [];
      return $id;
    }

    $st = $pdo->prepare("INSERT INTO security_roles (name, slug, description, is_active) VALUES (?,?,?,?)");
    $st->execute([$name, $slug, $description !== '' ? $description : null, $active]);
    return (int)$pdo->lastInsertId();
  }

  public static function permissions(): array
  {
    self::ensureSchema();
    return Database::conn()->query("
      SELECT p.*, parent.name AS parent_name
      FROM security_permissions p
      LEFT JOIN security_permissions parent ON parent.id=p.parent_id
      WHERE p.is_active=1
      ORDER BY p.menu_order ASC, p.name ASC
    ")->fetchAll();
  }

  public static function rolePermissionIds(int $roleId): array
  {
    self::ensureSchema();
    $st = Database::conn()->prepare("SELECT permission_id FROM security_role_permissions WHERE role_id=?");
    $st->execute([$roleId]);
    return array_map('intval', array_column($st->fetchAll(), 'permission_id'));
  }

  public static function syncRolePermissions(int $roleId, array $permissionIds): void
  {
    self::ensureSchema();
    if (!self::role($roleId)) {
      throw new RuntimeException('El perfil seleccionado no existe.');
    }
    $ids = array_values(array_unique(array_filter(array_map('intval', $permissionIds), static fn($id) => $id > 0)));
    $pdo = Database::conn();
    $pdo->beginTransaction();
    try {
      $pdo->prepare("DELETE FROM security_role_permissions WHERE role_id=?")->execute([$roleId]);
      $st = $pdo->prepare("INSERT INTO security_role_permissions (role_id, permission_id) VALUES (?,?)");
      foreach ($ids as $id) {
        $st->execute([$roleId, $id]);
      }
      $pdo->commit();
      self::$permissionCache = [];
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      throw $e;
    }
  }

  public static function users(): array
  {
    self::ensureSchema();
    return Database::conn()->query("
      SELECT u.id, u.document_type, u.document_number, u.first_name, u.last_name,
             u.role, u.is_active, u.security_role_id, sr.name AS security_role_name
      FROM users u
      LEFT JOIN security_roles sr ON sr.id=u.security_role_id
      ORDER BY u.first_name ASC, u.last_name ASC
    ")->fetchAll();
  }

  public static function assignUserRole(int $userId, int $roleId): void
  {
    self::ensureSchema();
    $role = self::role($roleId);
    if (!$role || (int)$role['is_active'] !== 1) {
      throw new RuntimeException('Selecciona un perfil activo.');
    }
    $st = Database::conn()->prepare("UPDATE users SET security_role_id=? WHERE id=?");
    $st->execute([$roleId, $userId]);
    if ($st->rowCount() === 0) {
      $exists = Database::conn()->prepare("SELECT id FROM users WHERE id=?");
      $exists->execute([$userId]);
      if (!$exists->fetchColumn()) {
        throw new RuntimeException('El usuario seleccionado no existe.');
      }
    }
    self::$permissionCache = [];
  }

  public static function userPermissionKeys(int $userId): array
  {
    self::ensureSchema();
    if (isset(self::$permissionCache[$userId])) {
      return self::$permissionCache[$userId];
    }
    $st = Database::conn()->prepare("
      SELECT p.permission_key
      FROM users u
      JOIN security_roles r ON r.id=u.security_role_id AND r.is_active=1
      JOIN security_role_permissions rp ON rp.role_id=r.id
      JOIN security_permissions p ON p.id=rp.permission_id AND p.is_active=1
      WHERE u.id=?
    ");
    $st->execute([$userId]);
    return self::$permissionCache[$userId] = array_column($st->fetchAll(), 'permission_key');
  }

  public static function hasPermission(int $userId, string $permissionKey): bool
  {
    return in_array($permissionKey, self::userPermissionKeys($userId), true);
  }

  public static function permissionForPath(string $path): ?array
  {
    self::ensureSchema();
    $path = rtrim($path, '/') ?: '/';
    $st = Database::conn()->prepare("
      SELECT *
      FROM security_permissions
      WHERE route IS NOT NULL
        AND is_active=1
        AND (? = route OR ? LIKE CONCAT(route, '/%'))
      ORDER BY CHAR_LENGTH(route) DESC
      LIMIT 1
    ");
    $st->execute([$path, $path]);
    return $st->fetch() ?: null;
  }

  public static function canAccessPath(int $userId, string $path): bool
  {
    $permission = self::permissionForPath($path);
    return !$permission || self::hasPermission($userId, (string)$permission['permission_key']);
  }

  public static function menuForUser(int $userId): array
  {
    self::ensureSchema();
    $keys = array_flip(self::userPermissionKeys($userId));
    $userSt = Database::conn()->prepare("SELECT role FROM users WHERE id=?");
    $userSt->execute([$userId]);
    $legacyRole = (string)$userSt->fetchColumn();
    $rows = self::permissions();
    $byParent = [];
    foreach ($rows as $row) {
      if ((int)$row['is_menu'] !== 1) {
        continue;
      }
      if ($legacyRole === 'admin' && $row['menu_context'] === 'worker') {
        continue;
      }
      $byParent[(int)($row['parent_id'] ?? 0)][] = $row;
    }
    $build = function (int $parentId) use (&$build, $byParent, $keys): array {
      $result = [];
      foreach ($byParent[$parentId] ?? [] as $row) {
        $children = $build((int)$row['id']);
        if (!isset($keys[$row['permission_key']]) && empty($children)) {
          continue;
        }
        $row['children'] = $children;
        $result[] = $row;
      }
      return $result;
    };
    return $build(0);
  }

  public static function log(?int $userId, string $eventType, ?string $route = null, ?string $moduleName = null, ?string $details = null): void
  {
    try {
      self::ensureSchema();
      $st = Database::conn()->prepare("
        INSERT INTO security_logs (user_id, event_type, route, module_name, ip_address, user_agent, details)
        VALUES (?,?,?,?,?,?,?)
      ");
      $st->execute([
        $userId,
        $eventType,
        $route,
        $moduleName,
        substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
        substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500) ?: null,
        $details !== null ? substr($details, 0, 500) : null,
      ]);
    } catch (Throwable $e) {
      // La auditoría no debe impedir el acceso al sistema.
    }
  }

  public static function logModuleAccess(int $userId, string $path): void
  {
    $permission = self::permissionForPath($path);
    self::log($userId, 'module_access', $path, $permission['name'] ?? $path);
  }

  public static function isLoginBlocked(string $documentNumber, int $maxAttempts = 5, int $minutes = 15): bool
  {
    self::ensureSchema();
    $documentNumber = trim($documentNumber);
    if ($documentNumber === '') {
      return false;
    }

    $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $minutes = max(1, min(60, $minutes));
    $maxAttempts = max(1, min(20, $maxAttempts));
    $st = Database::conn()->prepare("
      SELECT COUNT(*)
      FROM security_logs
      WHERE event_type='login_failed'
        AND created_at >= (NOW() - INTERVAL {$minutes} MINUTE)
        AND COALESCE(ip_address, '')=?
        AND details LIKE ?
    ");
    $st->execute([$ip, 'Documento: ' . $documentNumber . ';%']);
    return (int)$st->fetchColumn() >= $maxAttempts;
  }

  public static function paginatedLogs(array $filters = [], int $page = 1, int $perPage = 20): array
  {
    self::ensureSchema();
    $where = ['1=1'];
    $params = [];
    if (!empty($filters['event_type'])) {
      $where[] = 'l.event_type=?';
      $params[] = $filters['event_type'];
    }
    if (!empty($filters['user_id'])) {
      $where[] = 'l.user_id=?';
      $params[] = (int)$filters['user_id'];
    }
    if (!empty($filters['date_from'])) {
      $where[] = 'l.created_at>=?';
      $params[] = $filters['date_from'] . ' 00:00:00';
    }
    if (!empty($filters['date_to'])) {
      $where[] = 'l.created_at<?';
      $params[] = date('Y-m-d 00:00:00', strtotime($filters['date_to'] . ' +1 day'));
    }

    $whereSql = implode(' AND ', $where);
    $count = Database::conn()->prepare("
      SELECT COUNT(*)
      FROM security_logs l
      WHERE {$whereSql}
    ");
    $count->execute($params);
    $total = (int)$count->fetchColumn();

    $perPage = max(1, min(100, $perPage));
    $totalPages = max(1, (int)ceil($total / $perPage));
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;

    $st = Database::conn()->prepare("
      SELECT l.*, CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')) AS user_name,
             u.document_number
      FROM security_logs l
      LEFT JOIN users u ON u.id=l.user_id
      WHERE {$whereSql}
      ORDER BY l.id DESC
      LIMIT {$perPage} OFFSET {$offset}
    ");
    $st->execute($params);
    return [
      'rows' => $st->fetchAll(),
      'page' => $page,
      'per_page' => $perPage,
      'total' => $total,
      'total_pages' => $totalPages,
    ];
  }

  private static function slug(string $value): string
  {
    $value = trim($value);
    if (function_exists('iconv')) {
      $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
      if ($converted !== false) {
        $value = $converted;
      }
    }
    $value = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $value) ?? $value);
    return trim($value, '-') ?: 'perfil-' . time();
  }
}
