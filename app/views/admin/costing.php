<?php
require __DIR__ . '/../layouts/header.php';
Auth::requireRole('admin');
require_once __DIR__ . '/../../core/Csrf.php';
require_once __DIR__ . '/../../core/Pagination.php';

function costingMoney($value, int $decimals = 2): string {
  return 'S/ ' . number_format((float)$value, $decimals);
}

function costingNumber($value): string {
  return rtrim(rtrim(number_format((float)$value, 4, '.', ''), '0'), '.');
}

function costingSourceLabel(string $source): string {
  if ($source === 'makro') return 'Makro';
  if ($source === 'mercado') return 'Mercado';
  if ($source === 'proveedor') return 'Proveedor';
  return 'Menor costo';
}

$costingPagination = Pagination::paginateArray($costings, 'costing_page', 'costing_per_page');
$costingRows = $costingPagination['rows'];
$costingPaginationMeta = $costingPagination['meta'];
?>
<div class="app-shell d-flex">
  <?php require __DIR__ . '/../layouts/sidebar_admin.php'; ?>

  <div class="content p-4">
    <div class="page-toolbar mb-3">
      <div>
        <h3 class="mb-0">Costeo</h3>
        <div class="text-muted small">Calcula el costo de preparacion relacionando insumos con productos del catalogo.</div>
      </div>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreateCosting">+ Nuevo costeo</button>
    </div>

    <?php if (!empty($msg)): ?>
      <div class="alert alert-<?= Helpers::e($msg['type']) ?>"><?= Helpers::e($msg['text']) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm">
      <div class="card-body table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>ID</th>
              <th>Producto relacionado</th>
              <th>Costeo</th>
              <th>Porciones</th>
              <th>Costo total</th>
              <th>Costo unitario</th>
              <th>Insumos</th>
              <th style="width: 190px;">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($costingRows as $costing): ?>
              <tr>
                <td><?= (int)$costing['id'] ?></td>
                <td><?= Helpers::e($costing['product_name']) ?></td>
                <td>
                  <div class="fw-semibold"><?= Helpers::e($costing['title']) ?></div>
                  <div class="text-muted small"><?= Helpers::e($costing['notes'] ?? '') ?></div>
                </td>
                <td><?= Helpers::e(costingNumber($costing['portions'])) ?></td>
                <td><?= costingMoney($costing['total_cost'], 4) ?></td>
                <td><strong><?= costingMoney($costing['cost_per_portion'], 4) ?></strong></td>
                <td><?= count($costing['items']) ?></td>
                <td class="d-flex gap-2 flex-wrap">
                  <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalDetailCosting<?= (int)$costing['id'] ?>">Detalle</button>
                  <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalEditCosting<?= (int)$costing['id'] ?>">Editar</button>
                  <form method="POST" onsubmit="return confirm('Eliminar este costeo?');">
                    <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$costing['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($costingRows)): ?>
              <tr><td colspan="8" class="text-muted">Aun no hay costeos registrados.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?= Pagination::render($costingPaginationMeta) ?>
    </div>

    <?php foreach ($costingRows as $costing): ?>
      <div class="modal fade" id="modalDetailCosting<?= (int)$costing['id'] ?>" tabindex="-1">
        <div class="modal-dialog modal-xl">
          <div class="modal-content">
            <div class="modal-header">
              <div>
                <h5 class="modal-title"><?= Helpers::e($costing['title']) ?></h5>
                <div class="text-muted small">Costo unitario: <?= costingMoney($costing['cost_per_portion'], 4) ?></div>
              </div>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body table-responsive">
              <table class="table table-sm align-middle">
                <thead>
                  <tr>
                    <th>Insumo</th>
                    <th>Producto compra</th>
                    <th>Costos</th>
                    <th>Fuente</th>
                    <th>Rendimiento</th>
                    <th>Uso</th>
                    <th>Costo unit.</th>
                    <th>Total</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($costing['items'] as $item): ?>
                    <tr>
                      <td><?= Helpers::e($item['ingredient_name']) ?></td>
                      <td><?= Helpers::e($item['purchase_product_name'] ?? '-') ?></td>
                      <td class="small">
                        Makro: <?= costingMoney($item['cost_makro'], 4) ?><br>
                        Mercado: <?= costingMoney($item['cost_mercado'], 4) ?><br>
                        Proveedor: <?= costingMoney($item['cost_proveedor'], 4) ?>
                      </td>
                      <td><?= Helpers::e(costingSourceLabel($item['selected_source'])) ?></td>
                      <td><?= Helpers::e(costingNumber($item['yield_quantity']) . ' ' . ($item['yield_unit'] ?? '')) ?></td>
                      <td><?= Helpers::e(costingNumber($item['usage_quantity']) . ' ' . ($item['usage_unit'] ?? '')) ?></td>
                      <td><?= costingMoney($item['unit_cost'], 4) ?></td>
                      <td><strong><?= costingMoney($item['total_cost'], 4) ?></strong></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <?php $formCosting = $costing; $formId = 'modalEditCosting' . (int)$costing['id']; $formAction = 'update'; ?>
      <?php require __DIR__ . '/partials/costing_form_modal.php'; ?>
    <?php endforeach; ?>

    <?php
      $formCosting = [
        'id' => 0,
        'product_id' => 0,
        'title' => '',
        'portions' => 1,
        'notes' => '',
        'items' => [[
          'purchase_product_id' => null,
          'ingredient_name' => '',
          'package_description' => '',
          'cost_makro' => 0,
          'cost_mercado' => 0,
          'cost_proveedor' => 0,
          'selected_source' => 'auto',
          'yield_quantity' => 1,
          'yield_unit' => '',
          'usage_quantity' => 1,
          'usage_unit' => '',
        ]],
      ];
      $formId = 'modalCreateCosting';
      $formAction = 'create';
    ?>
    <?php require __DIR__ . '/partials/costing_form_modal.php'; ?>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const money = (value) => Number.parseFloat(value || '0') || 0;
  const selectedCost = (row) => {
    const source = row.querySelector('[data-source]')?.value || 'auto';
    const makro = money(row.querySelector('[data-cost-makro]')?.value);
    const mercado = money(row.querySelector('[data-cost-mercado]')?.value);
    const proveedor = money(row.querySelector('[data-cost-proveedor]')?.value);
    if (source === 'makro') return makro;
    if (source === 'mercado') return mercado;
    if (source === 'proveedor') return proveedor;
    return [makro, mercado, proveedor].filter((v) => v > 0).sort((a, b) => a - b)[0] || 0;
  };

  function recalculate(form) {
    let total = 0;
    form.querySelectorAll('[data-costing-row]').forEach((row) => {
      const cost = selectedCost(row);
      const yieldQty = Math.max(money(row.querySelector('[data-yield]')?.value), 1);
      const usageQty = money(row.querySelector('[data-usage]')?.value);
      const itemTotal = (cost / yieldQty) * usageQty;
      total += itemTotal;
      const target = row.querySelector('[data-row-total]');
      if (target) target.textContent = 'S/ ' + itemTotal.toFixed(4);
    });
    const portions = Math.max(money(form.querySelector('[name="portions"]')?.value), 1);
    const totalEl = form.querySelector('[data-costing-total]');
    const unitEl = form.querySelector('[data-costing-unit]');
    if (totalEl) totalEl.textContent = 'S/ ' + total.toFixed(4);
    if (unitEl) unitEl.textContent = 'S/ ' + (total / portions).toFixed(4);
  }

  document.querySelectorAll('[data-costing-form]').forEach((form) => {
    const table = form.querySelector('[data-costing-items]');
    const addButton = form.querySelector('[data-add-costing-item]');
    form.addEventListener('input', () => recalculate(form));
    form.addEventListener('change', () => recalculate(form));

    addButton?.addEventListener('click', () => {
      const first = table.querySelector('[data-costing-row]');
      const row = first.cloneNode(true);
      row.querySelectorAll('input').forEach((input) => input.value = input.type === 'number' ? (input.dataset.default || '0') : '');
      row.querySelectorAll('select').forEach((select) => select.value = select.dataset.default || '');
      table.appendChild(row);
      recalculate(form);
    });

    table?.addEventListener('click', (event) => {
      const button = event.target.closest('[data-remove-costing-item]');
      if (!button) return;
      const rows = table.querySelectorAll('[data-costing-row]');
      if (rows.length > 1) {
        button.closest('[data-costing-row]').remove();
      }
      recalculate(form);
    });

    recalculate(form);
  });
});
</script>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
