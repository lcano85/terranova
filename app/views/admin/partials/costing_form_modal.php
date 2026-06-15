<div class="modal fade" id="<?= Helpers::e($formId) ?>" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <form method="POST" data-costing-form>
        <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
        <input type="hidden" name="action" value="<?= Helpers::e($formAction) ?>">
        <?php if (!empty($formCosting['id'])): ?>
          <input type="hidden" name="id" value="<?= (int)$formCosting['id'] ?>">
        <?php endif; ?>

        <div class="modal-header">
          <div>
            <h5 class="modal-title"><?= $formAction === 'create' ? 'Nuevo costeo' : 'Editar costeo' ?></h5>
            <div class="text-muted small">Ej: Club sandwich, pan molde rinde 9 unidades, jamon rinde 50 laminas.</div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div class="row g-2 mb-3">
            <div class="col-md-4">
              <label class="form-label">Producto relacionado</label>
              <select class="form-select" name="product_id" required>
                <option value="">Selecciona producto</option>
                <?php foreach ($products as $product): ?>
                  <option value="<?= (int)$product['id'] ?>" <?= (int)($formCosting['product_id'] ?? 0) === (int)$product['id'] ? 'selected' : '' ?>>
                    <?= Helpers::e($product['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Nombre del costeo</label>
              <input class="form-control" name="title" value="<?= Helpers::e($formCosting['title'] ?? '') ?>" placeholder="Ej: Club sandwich" required>
            </div>
            <div class="col-md-2">
              <label class="form-label">Porciones</label>
              <input type="number" step="0.01" min="1" class="form-control" name="portions" value="<?= Helpers::e((string)($formCosting['portions'] ?? 1)) ?>" required>
            </div>
            <div class="col-md-2">
              <label class="form-label">Notas</label>
              <input class="form-control" name="notes" value="<?= Helpers::e($formCosting['notes'] ?? '') ?>">
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead>
                <tr>
                  <th style="min-width: 180px;">Insumo</th>
                  <th style="min-width: 180px;">Producto compra</th>
                  <th>Presentacion</th>
                  <th>Makro</th>
                  <th>Mercado</th>
                  <th>Proveedor</th>
                  <th>Fuente</th>
                  <th>Rinde</th>
                  <th>Unidad</th>
                  <th>Uso</th>
                  <th>Unidad</th>
                  <th>Total</th>
                  <th></th>
                </tr>
              </thead>
              <tbody data-costing-items>
                <?php foreach (($formCosting['items'] ?? []) as $item): ?>
                  <tr data-costing-row>
                    <td><input class="form-control form-control-sm" name="items[ingredient_name][]" value="<?= Helpers::e($item['ingredient_name'] ?? '') ?>" placeholder="Pan molde" required></td>
                    <td>
                      <select class="form-select form-select-sm" name="items[purchase_product_id][]" data-default="">
                        <option value="">Sin relacion</option>
                        <?php foreach ($products as $product): ?>
                          <option value="<?= (int)$product['id'] ?>" <?= (int)($item['purchase_product_id'] ?? 0) === (int)$product['id'] ? 'selected' : '' ?>>
                            <?= Helpers::e($product['name']) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </td>
                    <td><input class="form-control form-control-sm" name="items[package_description][]" value="<?= Helpers::e($item['package_description'] ?? '') ?>" placeholder="1 bolsa / 1 kg"></td>
                    <td><input type="number" step="0.0001" min="0" class="form-control form-control-sm" name="items[cost_makro][]" value="<?= Helpers::e((string)($item['cost_makro'] ?? 0)) ?>" data-cost-makro data-default="0"></td>
                    <td><input type="number" step="0.0001" min="0" class="form-control form-control-sm" name="items[cost_mercado][]" value="<?= Helpers::e((string)($item['cost_mercado'] ?? 0)) ?>" data-cost-mercado data-default="0"></td>
                    <td><input type="number" step="0.0001" min="0" class="form-control form-control-sm" name="items[cost_proveedor][]" value="<?= Helpers::e((string)($item['cost_proveedor'] ?? 0)) ?>" data-cost-proveedor data-default="0"></td>
                    <td>
                      <select class="form-select form-select-sm" name="items[selected_source][]" data-source data-default="auto">
                        <?php foreach (['auto' => 'Menor', 'makro' => 'Makro', 'mercado' => 'Mercado', 'proveedor' => 'Proveedor'] as $value => $label): ?>
                          <option value="<?= Helpers::e($value) ?>" <?= ($item['selected_source'] ?? 'auto') === $value ? 'selected' : '' ?>><?= Helpers::e($label) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </td>
                    <td><input type="number" step="0.0001" min="0" class="form-control form-control-sm" name="items[yield_quantity][]" value="<?= Helpers::e((string)($item['yield_quantity'] ?? 1)) ?>" data-yield data-default="1"></td>
                    <td><input class="form-control form-control-sm" name="items[yield_unit][]" value="<?= Helpers::e($item['yield_unit'] ?? '') ?>" placeholder="club / lamina"></td>
                    <td><input type="number" step="0.0001" min="0" class="form-control form-control-sm" name="items[usage_quantity][]" value="<?= Helpers::e((string)($item['usage_quantity'] ?? 1)) ?>" data-usage data-default="1"></td>
                    <td><input class="form-control form-control-sm" name="items[usage_unit][]" value="<?= Helpers::e($item['usage_unit'] ?? '') ?>" placeholder="unidad"></td>
                    <td><strong data-row-total>S/ 0.0000</strong></td>
                    <td><button type="button" class="btn btn-sm btn-outline-danger" data-remove-costing-item>Quitar</button></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <button type="button" class="btn btn-sm btn-outline-secondary" data-add-costing-item>+ Agregar insumo</button>

          <div class="row g-2 mt-3">
            <div class="col-md-6">
              <div class="border rounded p-3">
                <div class="text-muted small">Costo total</div>
                <div class="fs-5 fw-bold" data-costing-total>S/ 0.0000</div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="border rounded p-3">
                <div class="text-muted small">Costo por porcion</div>
                <div class="fs-5 fw-bold" data-costing-unit>S/ 0.0000</div>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn btn-primary"><?= $formAction === 'create' ? 'Registrar costeo' : 'Guardar cambios' ?></button>
        </div>
      </form>
    </div>
  </div>
</div>
