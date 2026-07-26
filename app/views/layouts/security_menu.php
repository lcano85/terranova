<?php
require_once __DIR__ . '/../../models/Security.php';

$securityMenuPath = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/') ?: '/';
$securityMenuUser = Auth::user();
$securityMenuItems = $securityMenuUser ? Security::menuForUser((int)$securityMenuUser['id']) : [];

$renderSecurityMenu = static function (array $items, string $path, int $level = 0) use (&$renderSecurityMenu): void {
  foreach ($items as $item) {
    $route = trim((string)($item['route'] ?? ''));
    $children = $item['children'] ?? [];
    $active = $route !== '' && ($path === $route || str_starts_with($path, $route . '/'));
    $childActive = false;
    foreach ($children as $child) {
      $childRoute = trim((string)($child['route'] ?? ''));
      if ($childRoute !== '' && ($path === $childRoute || str_starts_with($path, $childRoute . '/'))) {
        $childActive = true;
        break;
      }
    }

    if (!empty($children)) {
      ?>
      <div class="security-menu-group">
        <div class="security-menu-label <?= $childActive ? 'active' : '' ?>" style="padding-left: <?= 12 + ($level * 14) ?>px;">
          <?= Helpers::e($item['name']) ?>
        </div>
        <div class="security-submenu">
          <?php $renderSecurityMenu($children, $path, $level + 1); ?>
        </div>
      </div>
      <?php
      continue;
    }

    if ($route !== '') {
      ?>
      <a class="nav-link <?= $active ? 'active' : '' ?>"
         style="padding-left: <?= 12 + ($level * 14) ?>px;"
         href="<?= Helpers::e($route) ?>"><?= Helpers::e($item['name']) ?></a>
      <?php
    }
  }
};
?>
<?php $renderSecurityMenu($securityMenuItems, $securityMenuPath); ?>
