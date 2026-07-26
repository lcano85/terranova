<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Csrf.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../models/Security.php';

class SecurityController extends Controller
{
  public function users(): void
  {
    Auth::requirePermission('security.users');
    $msg = null;
    if (Helpers::isPost()) {
      Csrf::check();
      try {
        Security::assignUserRole((int)($_POST['user_id'] ?? 0), (int)($_POST['role_id'] ?? 0));
        $msg = ['type' => 'success', 'text' => 'Perfil asignado correctamente.'];
      } catch (Throwable $e) {
        $msg = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
      }
    }
    $users = Security::users();
    $roles = Security::roles(true);
    $this->view('security/users', compact('users', 'roles', 'msg'));
  }

  public function roles(): void
  {
    Auth::requirePermission('security.roles');
    $msg = null;
    if (Helpers::isPost()) {
      Csrf::check();
      try {
        Security::saveRole((int)($_POST['id'] ?? 0), $_POST);
        $msg = ['type' => 'success', 'text' => 'Perfil guardado correctamente.'];
      } catch (Throwable $e) {
        $msg = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
      }
    }
    $roles = Security::roles();
    $this->view('security/roles', compact('roles', 'msg'));
  }

  public function permissions(): void
  {
    Auth::requirePermission('security.permissions');
    $msg = null;
    $roles = Security::roles();
    $roleId = (int)($_GET['role_id'] ?? $_POST['role_id'] ?? ($roles[0]['id'] ?? 0));
    if (Helpers::isPost()) {
      Csrf::check();
      try {
        Security::syncRolePermissions($roleId, (array)($_POST['permission_ids'] ?? []));
        $msg = ['type' => 'success', 'text' => 'Permisos actualizados correctamente.'];
      } catch (Throwable $e) {
        $msg = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
      }
    }
    $permissions = Security::permissions();
    $selectedPermissionIds = Security::rolePermissionIds($roleId);
    $this->view('security/permissions', compact('roles', 'roleId', 'permissions', 'selectedPermissionIds', 'msg'));
  }

  public function logs(): void
  {
    Auth::requirePermission('security.logs');
    $filters = [
      'event_type' => trim((string)($_GET['event_type'] ?? '')),
      'user_id' => (int)($_GET['user_id'] ?? 0),
      'date_from' => trim((string)($_GET['date_from'] ?? '')),
      'date_to' => trim((string)($_GET['date_to'] ?? '')),
    ];
    $logs = Security::logs($filters);
    $users = Security::users();
    $this->view('security/logs', compact('logs', 'users', 'filters'));
  }
}
