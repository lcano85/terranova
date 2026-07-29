<div class="modal fade costing-scroll-modal costing-edit-modal" id="<?= Helpers::e($formId) ?>" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <form method="POST" data-costing-form>
        <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">
        <input type="hidden" name="action" value="<?= Helpers::e($formAction) ?>">
        <?php if (!empty($formCosting['id'])): ?><input type="hidden" name="id" value="<?= (int)$formCosting['id'] ?>"><?php endif; ?>
        <div class="modal-header">
          <div>
            <h5 class="modal-title"><?= $formAction === 'create' ? 'Nuevo escandallo' : 'Editar escandallo' ?></h5>
            <div class="text-muted small">El costo se calcula con la cantidad útil después de la merma.</div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3 mb-4">
            <div class="col-md-4">
              <label class="form-label">Receta *</label>
              <select class="form-select" name="recipe_id" data-recipe required>
                <option value="">Selecciona del recetario</option>
                <?php foreach ($recipes as $recipe): ?>
                  <option value="<?= (int)$recipe['id'] ?>"
                    data-title="<?= Helpers::e($recipe['title']) ?>"
                    data-ingredients="<?= Helpers::e(json_encode($recipe['ingredients'], JSON_UNESCAPED_UNICODE)) ?>"
                    <?= (int)($formCosting['recipe_id'] ?? 0)===(int)$recipe['id']?'selected':'' ?>>
                    <?= Helpers::e($recipe['title']) ?> · <?= Helpers::e(ucfirst($recipe['area_type'])) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">Al elegirla se cargan sus ingredientes automáticamente.</div>
            </div>
            <div class="col-md-3">
              <label class="form-label">Plato / costeo *</label>
              <input class="form-control" name="title" value="<?= Helpers::e($formCosting['title'] ?? '') ?>" required>
            </div>
            <div class="col-md-2">
              <label class="form-label">Rendimiento</label>
              <div class="input-group"><input type="number" step=".01" min=".01" class="form-control" name="portions" value="<?= Helpers::e((string)($formCosting['portions'] ?? 1)) ?>" required><span class="input-group-text">porc.</span></div>
            </div>
            <div class="col-md-3">
              <label class="form-label">Producto de venta (opcional)</label>
              <select class="form-select" name="product_id" data-product>
                <option value="">Sin relacionar</option>
                <?php foreach ($products as $product): ?>
                  <option value="<?= (int)$product['id'] ?>" data-price="<?= Helpers::e((string)($product['unit_price'] ?? 0)) ?>" <?= (int)($formCosting['product_id'] ?? 0)===(int)$product['id']?'selected':'' ?>><?= Helpers::e($product['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="d-flex justify-content-between align-items-center mb-2">
            <div><h6 class="mb-0">Ingredientes</h6><small class="text-muted">Ejemplo: compras 1 kg por S/ 12 y usas 100 g.</small></div>
            <button type="button" class="btn btn-sm btn-outline-primary" data-add-costing-item>+ Ingrediente</button>
          </div>
          <div class="table-responsive mb-4">
            <table class="table table-sm align-middle costing-editor">
              <thead><tr>
                <th style="min-width:190px">Ingrediente / insumo</th><th style="min-width:150px">Presentación</th>
                <th>Costo compra</th><th>Cant. compra</th><th>Unidad</th><th>Merma %</th>
                <th>Cant. usada</th><th>Unidad</th><th>Costo receta</th><th></th>
              </tr></thead>
              <tbody data-costing-items>
                <?php foreach (($formCosting['items'] ?? []) as $item): ?>
                <tr data-costing-row>
                  <td>
                    <input class="form-control form-control-sm mb-1" name="items[ingredient_name][]" value="<?= Helpers::e($item['ingredient_name'] ?? '') ?>" placeholder="Ingrediente de la receta" required>
                    <select class="form-select form-select-sm" name="items[supply_id][]" data-supply>
                      <option value="">Relacionar con insumo...</option>
                      <?php foreach ($supplies as $supply): ?>
                        <option value="<?= (int)$supply['id'] ?>" data-name="<?= Helpers::e($supply['name']) ?>" data-price="<?= Helpers::e((string)($supply['price'] ?? 0)) ?>" <?= (int)($item['supply_id'] ?? 0)===(int)$supply['id']?'selected':'' ?>><?= Helpers::e($supply['name']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                  <td><input class="form-control form-control-sm" name="items[package_description][]" value="<?= Helpers::e($item['package_description'] ?? '') ?>" placeholder="Bolsa, botella..."></td>
                  <td><div class="input-group input-group-sm"><span class="input-group-text">S/</span><input type="number" step=".0001" min="0" class="form-control" name="items[purchase_cost][]" value="<?= Helpers::e((string)($item['purchase_cost'] ?? 0)) ?>" data-purchase-cost></div></td>
                  <td><input type="number" step=".0001" min=".0001" class="form-control form-control-sm" name="items[purchase_quantity][]" value="<?= Helpers::e((string)($item['purchase_quantity'] ?? 1)) ?>" data-purchase-qty></td>
                  <td><?= costingUnitSelect('items[purchase_unit][]', $item['purchase_unit'] ?? 'und', 'data-purchase-unit') ?></td>
                  <td><input type="number" step=".01" min="0" max="99" class="form-control form-control-sm" name="items[waste_percent][]" value="<?= Helpers::e((string)($item['waste_percent'] ?? 0)) ?>" data-waste></td>
                  <td><input type="number" step=".0001" min="0" class="form-control form-control-sm" name="items[usage_quantity][]" value="<?= Helpers::e((string)($item['usage_quantity'] ?? 0)) ?>" data-usage-qty></td>
                  <td><?= costingUnitSelect('items[usage_unit][]', $item['usage_unit'] ?? 'und', 'data-usage-unit') ?></td>
                  <td><strong data-row-total>S/ 0.00</strong><small class="d-block text-danger" data-unit-error></small></td>
                  <td><button type="button" class="btn btn-sm btn-outline-danger" data-remove-costing-item>×</button></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div class="row g-3">
            <div class="col-lg-7">
              <div class="card bg-light border-0 h-100"><div class="card-body">
                <h6>Costos de preparación</h6>
                <div class="row g-2">
                  <div class="col-6 col-md-3"><label class="form-label small">Minutos personal</label><input type="number" step=".01" min="0" class="form-control" name="labor_minutes" value="<?= Helpers::e((string)($formCosting['labor_minutes'] ?? 0)) ?>" data-labor-minutes></div>
                  <div class="col-6 col-md-3"><label class="form-label small">Costo por hora</label><div class="input-group"><span class="input-group-text">S/</span><input type="number" step=".01" min="0" class="form-control" name="labor_hourly_cost" value="<?= Helpers::e((string)($formCosting['labor_hourly_cost'] ?? 0)) ?>" data-labor-hourly></div></div>
                  <div class="col-6 col-md-2"><label class="form-label small">Gas / energía</label><input type="number" step=".01" min="0" class="form-control" name="gas_cost" value="<?= Helpers::e((string)($formCosting['gas_cost'] ?? 0)) ?>" data-extra></div>
                  <div class="col-6 col-md-2"><label class="form-label small">Equipos</label><input type="number" step=".01" min="0" class="form-control" name="equipment_cost" value="<?= Helpers::e((string)($formCosting['equipment_cost'] ?? 0)) ?>" data-extra></div>
                  <div class="col-6 col-md-2"><label class="form-label small">Otros</label><input type="number" step=".01" min="0" class="form-control" name="other_cost" value="<?= Helpers::e((string)($formCosting['other_cost'] ?? 0)) ?>" data-extra></div>
                </div>
                <div class="row g-2 mt-2">
                  <div class="col-md-4"><label class="form-label small">Precio actual</label><div class="input-group"><span class="input-group-text">S/</span><input type="number" step=".01" min="0" class="form-control" name="selling_price" value="<?= Helpers::e((string)($formCosting['selling_price'] ?? 0)) ?>" data-selling-price></div></div>
                  <div class="col-md-4"><label class="form-label small">Margen objetivo</label><div class="input-group"><input type="number" step=".01" min="0" max="95" class="form-control" name="target_margin" value="<?= Helpers::e((string)($formCosting['target_margin'] ?? 30)) ?>" data-target-margin><span class="input-group-text">%</span></div></div>
                  <div class="col-md-4"><label class="form-label small">Notas</label><input class="form-control" name="notes" value="<?= Helpers::e($formCosting['notes'] ?? '') ?>"></div>
                </div>
              </div></div>
            </div>
            <div class="col-lg-5">
              <div class="card border-primary h-100"><div class="card-body">
                <div class="d-flex justify-content-between mb-2"><span>Ingredientes</span><strong data-ingredient-total>S/ 0.00</strong></div>
                <div class="d-flex justify-content-between mb-2"><span>Personal</span><strong data-labor-total>S/ 0.00</strong></div>
                <div class="d-flex justify-content-between border-top pt-2"><span>Costo total del lote</span><strong data-costing-total>S/ 0.00</strong></div>
                <div class="d-flex justify-content-between"><span>Costo por porción</span><strong data-costing-unit>S/ 0.00</strong></div>
                <hr>
                <div class="d-flex justify-content-between"><span>Precio sugerido</span><strong class="text-primary fs-5" data-suggested-price>S/ 0.00</strong></div>
                <div class="d-flex justify-content-between"><span>Margen con precio actual</span><strong data-current-margin>—</strong></div>
                <small class="text-muted d-block mt-2">Margen = (precio − costo) ÷ precio. No incluye IGV salvo que lo registres en “otros”.</small>
              </div></div>
            </div>
          </div>
        </div>
        <div class="modal-footer"><button type="submit" class="btn btn-primary"><?= $formAction === 'create'?'Guardar escandallo':'Guardar cambios' ?></button></div>
      </form>
    </div>
  </div>
</div>
