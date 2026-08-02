<?php
require __DIR__ . '/../layouts/header.php';
Auth::requireRole('admin');
require_once __DIR__ . '/../../core/Csrf.php';
require_once __DIR__ . '/../../core/Pagination.php';
$money=static fn($v):string=>$v===null?'—':'S/ '.number_format((float)$v,2);
$pagination=Pagination::paginateArray($products,'delivery_page','delivery_per_page',[10,20,50,100]);
$visibleProducts=$pagination['rows'];
?>
<div class="app-shell d-flex">
<?php require __DIR__ . '/../layouts/sidebar_admin.php';?>
<div class="content p-4">
  <div class="page-toolbar mb-3"><div><h3 class="mb-0">Precios delivery</h3><div class="text-muted small">Compara el precio carta con PedidosYa y Rappi en una sola vista.</div></div></div>
  <?php if(!empty($msg)):?><div class="alert alert-<?=Helpers::e($msg['type'])?>"><?=Helpers::e($msg['text'])?></div><?php endif;?>

  <div class="card shadow-sm mb-3 border-primary"><div class="card-body">
    <form method="POST" class="row g-3 align-items-end" id="commissionForm">
      <input type="hidden" name="_csrf" value="<?=Helpers::e(Csrf::token())?>"><input type="hidden" name="action" value="update_commissions">
      <div class="col-lg-4"><h5 class="mb-1">Comisiones</h5><div class="text-muted small">Modifica los porcentajes para recalcular los precios sugeridos.</div></div>
      <?php foreach($platforms as $platform):?><div class="col-6 col-lg-3"><label class="form-label"><?=Helpers::e($platform['name'])?></label><div class="input-group"><input class="form-control" type="number" min="0" max="99.99" step=".01" name="commissions[<?=(int)$platform['id']?>]" value="<?=Helpers::e((string)$platform['commission_percent'])?>" data-commission-platform="<?=(int)$platform['id']?>" required><span class="input-group-text">%</span></div></div><?php endforeach;?>
      <div class="col-lg-2 d-grid"><button class="btn btn-primary">Guardar comisiones</button></div>
    </form>
    <div class="form-text mt-2">El precio sugerido busca que recibas el mismo importe del precio carta después de descontar la comisión.</div>
  </div></div>

  <div class="card shadow-sm mb-3"><div class="card-body"><form method="GET" class="row g-2 align-items-end">
    <div class="col-md-4"><label class="form-label">Categoría</label><select class="form-select" name="category_id"><option value="">Todas</option><?php foreach($categories as $category):?><option value="<?=(int)$category['id']?>" <?=$categoryId===(int)$category['id']?'selected':''?>><?=Helpers::e($category['name'])?></option><?php endforeach;?></select></div>
    <div class="col-md-5"><label class="form-label">Producto</label><input class="form-control" name="q" value="<?=Helpers::e($search)?>" placeholder="Buscar producto"></div>
    <div class="col-md-3 d-flex gap-2"><button class="btn btn-outline-primary flex-grow-1">Filtrar</button><a class="btn btn-outline-secondary" href="<?=Helpers::e(BASE_URL.'/admin/delivery-pricing')?>">Limpiar</a></div>
  </form></div></div>

  <div class="card shadow-sm"><div class="card-body table-responsive">
    <table class="table align-middle" style="min-width:1250px"><thead><tr><th>Producto</th><th class="text-end">Precio carta</th><?php foreach($platforms as $platform):?><th style="min-width:275px"><?=Helpers::e($platform['name'])?></th><?php endforeach;?><th></th></tr></thead><tbody>
    <?php foreach($visibleProducts as $product):$formId='deliveryProduct'.(int)$product['id'];?>
      <tr data-delivery-product data-card-price="<?=Helpers::e((string)($product['unit_price']??''))?>">
        <td><div class="fw-semibold"><?=Helpers::e($product['name'])?></div><div class="text-muted small"><?=Helpers::e($product['category_name']??'Sin categoría')?></div></td>
        <td class="text-end fw-semibold"><?=$money($product['unit_price'])?></td>
        <?php foreach($product['platforms'] as $platform):?><td data-platform-cell="<?=(int)$platform['id']?>">
          <div class="small text-muted">Sugerido: <strong class="text-primary" data-suggested><?=$money($platform['suggested_price'])?></strong></div>
          <div class="input-group input-group-sm my-1"><span class="input-group-text">S/</span><input class="form-control" type="number" min="0" step=".01" name="prices[<?=(int)$platform['id']?>]" value="<?=number_format((float)($platform['effective_price']??0),2,'.','')?>" form="<?=$formId?>" data-published data-auto="<?=$platform['published_price']===null?'1':'0'?>" required><button class="btn btn-outline-secondary" type="button" data-use-suggested>Usar sugerido</button></div>
          <div class="small">Comisión: <span data-fee><?=$money($platform['commission_amount'])?></span> · Neto: <strong data-net><?=$money($platform['net_received'])?></strong></div>
        </td><?php endforeach;?>
        <td><form method="POST" id="<?=$formId?>"><input type="hidden" name="_csrf" value="<?=Helpers::e(Csrf::token())?>"><input type="hidden" name="action" value="save_product_prices"><input type="hidden" name="product_id" value="<?=(int)$product['id']?>"><button class="btn btn-sm btn-primary">Guardar</button></form></td>
      </tr>
    <?php endforeach;?>
    <?php if(empty($visibleProducts)):?><tr><td colspan="<?=3+count($platforms)?>" class="text-muted text-center py-4">No hay productos con los filtros seleccionados.</td></tr><?php endif;?>
    </tbody></table><?=Pagination::render($pagination['meta'])?>
  </div></div>
</div></div>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const money=v=>'S/ '+Number(v||0).toLocaleString('es-PE',{minimumFractionDigits:2,maximumFractionDigits:2});
 const commission=id=>Number(document.querySelector('[data-commission-platform="'+id+'"]')?.value||0);
 const recalc=row=>row.querySelectorAll('[data-platform-cell]').forEach(cell=>{const c=commission(cell.dataset.platformCell),card=Number(row.dataset.cardPrice||0),suggested=c<100?card/(1-c/100):0,input=cell.querySelector('[data-published]');if(input.dataset.auto==='1')input.value=suggested.toFixed(2);const price=Number(input.value||0);cell.querySelector('[data-suggested]').textContent=money(suggested);cell.querySelector('[data-fee]').textContent=money(price*c/100);cell.querySelector('[data-net]').textContent=money(price*(1-c/100));cell.dataset.suggested=suggested.toFixed(2)});
 document.querySelectorAll('[data-delivery-product]').forEach(row=>{recalc(row);row.querySelectorAll('[data-published]').forEach(input=>input.addEventListener('input',()=>{input.dataset.auto='0';recalc(row)}));row.querySelectorAll('[data-use-suggested]').forEach(button=>button.addEventListener('click',()=>{const cell=button.closest('[data-platform-cell]'),input=cell.querySelector('[data-published]');input.dataset.auto='1';recalc(row)}))});
 document.querySelectorAll('[data-commission-platform]').forEach(input=>input.addEventListener('input',()=>document.querySelectorAll('[data-delivery-product]').forEach(recalc)));
});
</script>
<?php require __DIR__ . '/../layouts/footer.php';?>
