<?php
require __DIR__ . '/../layouts/header.php';
Auth::requireRole('admin');
require_once __DIR__ . '/../../core/Csrf.php';
function costingMoney($v,int $d=2): string { return 'S/ '.number_format((float)$v,$d); }
function costingNumber($v): string { return rtrim(rtrim(number_format((float)$v,4,'.',''),'0'),'.'); }
function costingUnitSelect(string $name,string $selected,string $data=''): string {
  $html='<select class="form-select form-select-sm" name="'.Helpers::e($name).'" '.$data.'>';
  foreach(['und'=>'und','g'=>'g','kg'=>'kg','ml'=>'ml','l'=>'l'] as $value=>$label) $html.='<option value="'.$value.'" '.($selected===$value?'selected':'').'>'.$label.'</option>';
  return $html.'</select>';
}
$totalDishes=count($costings);
$avgCost=$totalDishes?array_sum(array_column($costings,'cost_per_portion'))/$totalDishes:0;
$priced=array_filter($costings,fn($c)=>(float)$c['selling_price']>0);
$avgMargin=$priced?array_sum(array_map(fn($c)=>(((float)$c['selling_price']-(float)$c['cost_per_portion'])/(float)$c['selling_price'])*100,$priced))/count($priced):0;
?>
<style>
.costing-kpi{border:0;border-left:4px solid #0d6efd}.costing-editor td{vertical-align:middle}.costing-editor input,.costing-editor select{min-width:78px}
.profit-positive{color:#198754}.profit-negative{color:#dc3545}.costing-card{transition:.15s}.costing-card:hover{transform:translateY(-2px)}
.costing-scroll-modal .modal-body{max-height:calc(100vh - 190px);overflow-y:auto;scrollbar-gutter:stable}
.costing-scroll-modal .modal-body::-webkit-scrollbar{width:10px}.costing-scroll-modal .modal-body::-webkit-scrollbar-thumb{background:#adb5bd;border-radius:10px;border:2px solid #fff}
.costing-scroll-modal .modal-body::-webkit-scrollbar-track{background:#f1f3f5}
.costing-edit-modal form[data-costing-form]{display:flex;flex-direction:column;min-height:0;max-height:calc(100vh - 2rem)}
.costing-edit-modal form[data-costing-form]>.modal-body{min-height:0}
@media(max-width:767.98px){.costing-scroll-modal .modal-body{max-height:calc(100vh - 150px)}}
</style>
<div class="app-shell d-flex">
<?php require __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="content p-4">
  <div class="page-toolbar mb-3">
    <div><h3 class="mb-0">Costeo de platos</h3><div class="text-muted small">Escandallos conectados al recetario: ingredientes, merma, personal, gas, equipos y margen.</div></div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreateCosting">+ Nuevo costeo</button>
  </div>
  <?php if(!empty($msg)): ?><div class="alert alert-<?= Helpers::e($msg['type']) ?>"><?= Helpers::e($msg['text']) ?></div><?php endif; ?>
  <div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card costing-kpi shadow-sm"><div class="card-body"><small class="text-muted">Platos costeados</small><div class="fs-3 fw-bold"><?= $totalDishes ?></div></div></div></div>
    <div class="col-md-3"><div class="card costing-kpi shadow-sm"><div class="card-body"><small class="text-muted">Costo promedio / porción</small><div class="fs-3 fw-bold"><?= costingMoney($avgCost) ?></div></div></div></div>
    <div class="col-md-3"><div class="card costing-kpi shadow-sm"><div class="card-body"><small class="text-muted">Margen promedio actual</small><div class="fs-3 fw-bold"><?= $priced?number_format($avgMargin,1).'%':'—' ?></div></div></div></div>
    <div class="col-md-3"><div class="card costing-kpi shadow-sm"><div class="card-body"><small class="text-muted">Recetas disponibles</small><div class="fs-3 fw-bold"><?= count($recipes) ?></div></div></div></div>
  </div>
  <form class="card card-body shadow-sm mb-3" method="GET"><div class="row g-2"><div class="col-md-9"><input class="form-control" name="q" value="<?= Helpers::e($search) ?>" placeholder="Buscar plato o receta"></div><div class="col-md-3 d-grid"><button class="btn btn-outline-primary">Buscar</button></div></div></form>
  <?php if(!$costings): ?>
    <div class="card shadow-sm"><div class="card-body text-center py-5"><div class="fs-1">🧾</div><h5>Aún no hay platos costeados</h5><p class="text-muted mb-3">Empieza eligiendo una receta; sus ingredientes se cargarán automáticamente.</p><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreateCosting">Crear primer costeo</button></div></div>
  <?php else: ?>
  <div class="row g-3">
    <?php foreach($costings as $c):
      $price=(float)$c['selling_price']; $unit=(float)$c['cost_per_portion'];
      $margin=$price>0?(($price-$unit)/$price)*100:null; $profit=$price-$unit;
    ?>
    <div class="col-xl-6"><div class="card shadow-sm costing-card h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between gap-2">
          <div><span class="badge text-bg-<?= ($c['area_type']??'')==='barra'?'info':'warning' ?> mb-2"><?= Helpers::e(ucfirst($c['area_type']??'Receta')) ?></span><h5 class="mb-1"><?= Helpers::e($c['title']) ?></h5><small class="text-muted"><?= Helpers::e($c['recipe_title']??'') ?> · <?= costingNumber($c['portions']) ?> porción(es)</small></div>
          <div class="text-end"><small class="text-muted">Costo / porción</small><div class="fs-4 fw-bold"><?= costingMoney($unit) ?></div></div>
        </div>
        <div class="row g-2 mt-3 text-center">
          <div class="col-3"><div class="bg-light rounded p-2"><small class="d-block text-muted">Ingredientes</small><strong><?= costingMoney($c['ingredient_cost']) ?></strong></div></div>
          <div class="col-3"><div class="bg-light rounded p-2"><small class="d-block text-muted">Preparación</small><strong><?= costingMoney((float)$c['labor_cost']+(float)$c['gas_cost']+(float)$c['equipment_cost']+(float)$c['other_cost']) ?></strong></div></div>
          <div class="col-3"><div class="bg-light rounded p-2"><small class="d-block text-muted">Precio actual</small><strong><?= $price>0?costingMoney($price):'—' ?></strong></div></div>
          <div class="col-3"><div class="bg-light rounded p-2"><small class="d-block text-muted">Margen</small><strong class="<?= $margin!==null&&$margin>=0?'profit-positive':'profit-negative' ?>"><?= $margin!==null?number_format($margin,1).'%':'—' ?></strong></div></div>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-3"><span>Precio sugerido (<?= costingNumber($c['target_margin']) ?>% margen): <strong class="text-primary"><?= costingMoney($c['suggested_price']) ?></strong></span><span class="<?= $profit>=0?'profit-positive':'profit-negative' ?>"><?= $price>0?'Utilidad '.costingMoney($profit):'' ?></span></div>
      </div>
      <div class="card-footer bg-white d-flex gap-2">
        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#detail<?= (int)$c['id'] ?>">Ver desglose</button>
        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#edit<?= (int)$c['id'] ?>">Editar</button>
        <form method="POST" class="ms-auto" onsubmit="return confirm('¿Eliminar este costeo?')"><input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-sm btn-outline-danger">Eliminar</button></form>
      </div>
    </div></div>
    <div class="modal fade costing-scroll-modal" id="detail<?= (int)$c['id'] ?>" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><div><h5 class="modal-title"><?= Helpers::e($c['title']) ?></h5><small class="text-muted">Ficha de costo por ingrediente</small></div><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
      <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Ingrediente</th><th>Insumo</th><th>Compra</th><th>Merma</th><th>Uso</th><th>Costo</th></tr></thead><tbody>
      <?php foreach($c['items'] as $i): ?><tr><td><?= Helpers::e($i['ingredient_name']) ?></td><td><?= Helpers::e($i['supply_name']??'Sin relacionar') ?></td><td><?= costingMoney($i['purchase_cost'],4) ?> / <?= costingNumber($i['purchase_quantity']).' '.Helpers::e($i['purchase_unit']) ?></td><td><?= costingNumber($i['waste_percent']) ?>%</td><td><?= costingNumber($i['usage_quantity']).' '.Helpers::e($i['usage_unit']) ?></td><td><strong><?= costingMoney($i['total_cost'],4) ?></strong></td></tr><?php endforeach; ?>
      </tbody></table></div>
      <div class="row g-2 mt-2"><?php foreach(['ingredient_cost'=>'Ingredientes','labor_cost'=>'Personal','gas_cost'=>'Gas / energía','equipment_cost'=>'Equipos','other_cost'=>'Otros','total_cost'=>'Total lote'] as $key=>$label): ?><div class="col-md-2"><div class="border rounded p-2"><small class="text-muted"><?= $label ?></small><div class="fw-bold"><?= costingMoney($c[$key]) ?></div></div></div><?php endforeach; ?></div>
    </div></div></div></div>
    <?php $formCosting=$c;$formId='edit'.(int)$c['id'];$formAction='update';require __DIR__.'/partials/costing_form_modal.php'; ?>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <?php $formCosting=['id'=>0,'recipe_id'=>0,'product_id'=>0,'title'=>'','portions'=>1,'labor_minutes'=>0,'labor_hourly_cost'=>0,'gas_cost'=>0,'equipment_cost'=>0,'other_cost'=>0,'selling_price'=>0,'target_margin'=>30,'notes'=>'','items'=>[['ingredient_name'=>'','supply_id'=>null,'package_description'=>'','purchase_cost'=>0,'purchase_quantity'=>1,'purchase_unit'=>'und','waste_percent'=>0,'usage_quantity'=>0,'usage_unit'=>'und']]];$formId='modalCreateCosting';$formAction='create';require __DIR__.'/partials/costing_form_modal.php'; ?>
</main></div>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const n=v=>Number.parseFloat(v||'0')||0, money=v=>'S/ '+v.toFixed(2);
 const dim=u=>['kg','g'].includes(u)?'w':['l','ml'].includes(u)?'v':'u';
 const base=(q,u)=>['kg','l'].includes(u)?q*1000:q;
 function calc(form){
  let ingredients=0, valid=true;
  form.querySelectorAll('[data-costing-row]').forEach(row=>{
   const pu=row.querySelector('[data-purchase-unit]').value, uu=row.querySelector('[data-usage-unit]').value;
   const error=row.querySelector('[data-unit-error]'); let total=0;
   if(dim(pu)!==dim(uu)){ error.textContent='Unidades incompatibles'; valid=false; }
   else{ error.textContent=''; const usable=base(n(row.querySelector('[data-purchase-qty]').value),pu)*(1-n(row.querySelector('[data-waste]').value)/100); total=n(row.querySelector('[data-purchase-cost]').value)/Math.max(usable,.0001)*base(n(row.querySelector('[data-usage-qty]').value),uu); }
   row.querySelector('[data-row-total]').textContent=money(total); ingredients+=total;
  });
  const labor=n(form.querySelector('[data-labor-minutes]').value)/60*n(form.querySelector('[data-labor-hourly]').value);
  let extras=0; form.querySelectorAll('[data-extra]').forEach(x=>extras+=n(x.value));
  const total=ingredients+labor+extras, portions=Math.max(n(form.elements.portions.value),.01), unit=total/portions;
  const margin=Math.min(95,n(form.querySelector('[data-target-margin]').value)), suggested=unit/Math.max(.05,1-margin/100), price=n(form.querySelector('[data-selling-price]').value);
  form.querySelector('[data-ingredient-total]').textContent=money(ingredients);form.querySelector('[data-labor-total]').textContent=money(labor);form.querySelector('[data-costing-total]').textContent=money(total);form.querySelector('[data-costing-unit]').textContent=money(unit);form.querySelector('[data-suggested-price]').textContent=money(suggested);
  const current=form.querySelector('[data-current-margin]');current.textContent=price>0?(((price-unit)/price)*100).toFixed(1)+'%':'—';current.className=price>=unit?'profit-positive':'profit-negative';
  form.querySelector('button[type=submit]').disabled=!valid;
 }
 const rowHtml=form=>form.querySelector('[data-costing-row]').outerHTML;
 document.querySelectorAll('[data-costing-form]').forEach(form=>{
  const body=form.querySelector('[data-costing-items]');form.dataset.blankRow=rowHtml(form);
  const recalc=()=>calc(form);form.addEventListener('input',recalc);form.addEventListener('change',e=>{
   if(e.target.matches('[data-supply]')){const o=e.target.selectedOptions[0],row=e.target.closest('tr');if(o?.value){row.querySelector('[data-purchase-cost]').value=o.dataset.price||0;if(!row.querySelector('[name="items[ingredient_name][]"]').value)row.querySelector('[name="items[ingredient_name][]"]').value=o.dataset.name||'';}}
   if(e.target.matches('[data-product]')){const o=e.target.selectedOptions[0];if(o?.value)form.querySelector('[data-selling-price]').value=o.dataset.price||0;}
   if(e.target.matches('[data-recipe]')){const o=e.target.selectedOptions[0];if(o?.value){form.elements.title.value=o.dataset.title||'';let list=[];try{list=JSON.parse(o.dataset.ingredients||'[]')}catch{}body.innerHTML='';list.forEach(name=>{body.insertAdjacentHTML('beforeend',form.dataset.blankRow);const r=body.lastElementChild;r.querySelectorAll('input').forEach(x=>x.value='');r.querySelectorAll('select').forEach(x=>x.selectedIndex=0);r.querySelector('[name="items[ingredient_name][]"]').value=name;r.querySelector('[data-purchase-qty]').value=1;});}}
   recalc();
  });
  form.querySelector('[data-add-costing-item]').addEventListener('click',()=>{body.insertAdjacentHTML('beforeend',form.dataset.blankRow);const r=body.lastElementChild;r.querySelectorAll('input').forEach(x=>x.value='');r.querySelectorAll('select').forEach(x=>x.selectedIndex=0);r.querySelector('[data-purchase-qty]').value=1;recalc();});
  body.addEventListener('click',e=>{const b=e.target.closest('[data-remove-costing-item]');if(b&&body.children.length>1){b.closest('tr').remove();recalc();}});
  recalc();
 });
});
</script>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
