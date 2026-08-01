<?php
require_once __DIR__ . '/../../models/Security.php';

$securityMenuPath = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/') ?: '/';
$securityMenuUser = Auth::user();
$securityMenuItems = $securityMenuUser ? Security::menuForUser((int)$securityMenuUser['id']) : [];
$securityMenuInstance = ($securityMenuInstance ?? 0) + 1;
$securityMenuGroupIndex = 0;

$renderSecurityMenu = static function (array $items, string $path, int $level = 0) use (&$renderSecurityMenu, &$securityMenuGroupIndex, $securityMenuInstance): void {
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
      $securityMenuGroupIndex++;
      $collapseId = 'securityMenu' . $securityMenuInstance . 'Group' . $securityMenuGroupIndex;
      $menuKey = (string)($item['key'] ?? $item['name']);
      ?>
      <div class="security-menu-group" data-security-menu-group="<?= Helpers::e($menuKey) ?>">
        <button class="security-menu-label security-menu-toggle <?= $childActive ? 'active' : 'collapsed' ?>"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#<?= Helpers::e($collapseId) ?>"
                aria-expanded="<?= $childActive ? 'true' : 'false' ?>"
                aria-controls="<?= Helpers::e($collapseId) ?>"
                style="padding-left: <?= 12 + ($level * 14) ?>px;">
          <span><?= Helpers::e($item['name']) ?></span>
          <span class="security-menu-chevron" aria-hidden="true"></span>
        </button>
        <div class="security-submenu collapse <?= $childActive ? 'show' : '' ?>"
             id="<?= Helpers::e($collapseId) ?>"
             data-security-menu-collapse="<?= Helpers::e($menuKey) ?>">
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
