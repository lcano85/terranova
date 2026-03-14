<?php
require __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../../core/Csrf.php';
?>
<div class="container py-5" style="max-width: 520px;">
  <div class="card shadow-sm">
    <div class="card-body">
      <h4 class="mb-1">Marcación de Asistencia</h4>
      <div class="text-muted mb-3">Entrada / Salida</div>

      <?php if (!empty($msg)): ?>
        <div class="alert alert-<?= Helpers::e($msg['type']) ?>"><?= Helpers::e($msg['text']) ?></div>
      <?php endif; ?>

      <form method="POST">
        <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">

        <div class="mb-3">
          <label class="form-label">Tipo documento</label>
          <select class="form-select" name="document_type" required>
            <option value="dni">DNI</option>
            <option value="cedula">Cédula</option>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">Nro documento</label>
          <input class="form-control" name="document_number" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Tipo de marcación</label>
          <select class="form-select" name="mark_type" required>
            <option value="in">Entrada</option>
            <option value="out">Salida</option>
          </select>
        </div>

        <input type="hidden" name="latitude" id="latitude">
        <input type="hidden" name="longitude" id="longitude">


        <button class="btn btn-success w-100">Marcar</button>
      </form>

      <div class="mt-3">
        <button type="button" class="btn btn-outline-primary w-100" id="btnPromo" data-bs-toggle="modal" data-bs-target="#modalPromo">
          Ver promoción del día
        </button>
      </div>

      <div class="text-muted small mt-3">
        ¿Admin o trabajador? <a href="/login">Inicia sesión</a>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalPromo" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Promoción del día</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="text-muted small" id="promoMeta">Cargando...</div>
        <h6 class="mt-2" id="promoTitle" style="display:none;"></h6>
        <div class="mt-2" id="promoContent" style="white-space: pre-wrap;"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<script>
  (function(){
    const modal = document.getElementById('modalPromo');
    if (!modal) return;

    const meta = document.getElementById('promoMeta');
    const title = document.getElementById('promoTitle');
    const content = document.getElementById('promoContent');

    modal.addEventListener('show.bs.modal', async function(){
      meta.textContent = 'Cargando...';
      title.style.display = 'none';
      title.textContent = '';
      content.textContent = '';

      try {
        const res = await fetch('/promotions/today', { headers: { 'Accept': 'application/json' }});
        const data = await res.json();

        if (!data || !data.ok) {
          meta.textContent = 'No se pudo cargar la promoción.';
          return;
        }

        meta.textContent = `${data.weekdayLabel ?? ''} • ${data.shiftLabel ?? ''}`.trim();

        if (!data.hasPromo) {
          content.textContent = data.message || 'No hay promoción configurada para hoy.';
          return;
        }

        if (data.title) {
          title.textContent = data.title;
          title.style.display = 'block';
        }
        content.textContent = data.content || '';
      } catch (e) {
        meta.textContent = 'No se pudo cargar la promoción.';
        content.textContent = '';
      }
    });
  })();
</script>
<?php require __DIR__ . '/../layouts/footer.php'; ?>