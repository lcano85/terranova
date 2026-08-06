<?php require __DIR__.'/../layouts/header.php'; Auth::requireRole('worker'); ?>
<div class="app-shell d-flex"><?php require __DIR__.'/../layouts/sidebar_worker.php';?><div class="content p-4">
  <div class="page-toolbar mb-3"><div><h3 class="mb-0">Mis funciones</h3><div class="text-muted small">Responsabilidades publicadas para tu área de trabajo.</div></div></div>
  <?php if(!$functions):?><div class="card shadow-sm"><div class="card-body text-center text-muted py-5">No hay funciones publicadas para tu área.</div></div><?php endif;?>
  <div class="row g-3"><?php foreach($functions as $item):?><div class="col-lg-6"><div class="card shadow-sm h-100 border-primary"><div class="card-body"><span class="badge text-bg-info mb-2"><?=Helpers::e($item['area_name'])?></span><h4><?=Helpers::e($item['title'])?></h4><div style="white-space:pre-line"><?=Helpers::e($item['description'])?></div><div class="text-muted small mt-3">Actualizado: <?=Helpers::e(date('d/m/Y H:i',strtotime($item['updated_at'])))?></div></div></div></div><?php endforeach;?></div>
</div></div><?php require __DIR__.'/../layouts/footer.php';?>
