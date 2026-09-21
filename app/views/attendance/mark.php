<?php
require __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../../core/Csrf.php';
?>
<div class="container py-5" style="max-width: 520px;">
  <div class="card shadow-sm">
    <div class="card-body">
      <h4 class="mb-1">Marcacion de Asistencia</h4>
      <div class="text-muted mb-3">Entrada / Salida</div>

      <?php if (!empty($msg)): ?>
        <div class="alert alert-<?= Helpers::e($msg['type']) ?>"><?= Helpers::e($msg['text']) ?></div>
      <?php endif; ?>

      <form method="POST" id="attendanceForm">
        <input type="hidden" name="_csrf" value="<?= Helpers::e(Csrf::token()) ?>">

        <div class="mb-3">
          <label class="form-label">Tipo documento</label>
          <select class="form-select" name="document_type" required>
            <option value="dni">DNI</option>
            <option value="cedula">Cedula</option>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">Nro documento</label>
          <input class="form-control" name="document_number" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Tipo de marcacion</label>
          <select class="form-select" name="mark_type" required>
            <option value="in">Entrada</option>
            <option value="out">Salida</option>
          </select>
        </div>

        <input type="hidden" name="latitude" id="latitude">
        <input type="hidden" name="longitude" id="longitude">
        <input type="hidden" name="accuracy" id="accuracy">
        <div class="alert alert-info small" id="locationStatus" role="status" aria-live="polite">
          Para marcar entrada o salida, permite tu ubicación precisa y ubícate dentro de <?= (int)$locationReference['radius_meters'] ?> metros del punto de referencia de Terranova.
        </div>
        <noscript><div class="alert alert-danger">Activa JavaScript y el permiso de ubicación para marcar asistencia.</div></noscript>

        <button type="submit" class="btn btn-success w-100" id="btnMark" aria-live="polite">
          <span class="spinner-border spinner-border-sm me-2 d-none" id="markSpinner" aria-hidden="true"></span>
          <span id="markButtonText">Marcar</span>
        </button>
      </form>

      <div class="mt-3">
        <button type="button" class="btn btn-outline-primary w-100" id="btnPromo" data-bs-toggle="modal" data-bs-target="#modalPromo">
          Ver promociones
        </button>
      </div>

      <div class="mt-2">
        <a href="/tasks/board" class="btn btn-outline-secondary w-100">Ver tablero de tareas</a>
      </div>

      <div class="text-muted small mt-3">
        Admin o trabajador? <a href="/login">Inicia sesion</a>
      </div>
    </div>
  </div>
</div>

<?php if (($msg['type'] ?? '') === 'success'): ?>
<div class="modal fade" id="modalMarkSuccess" tabindex="-1" aria-labelledby="modalMarkSuccessTitle" aria-describedby="modalMarkSuccessBody" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title text-success" id="modalMarkSuccessTitle">Marcación registrada</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body" id="modalMarkSuccessBody">
        <p><?= Helpers::e($msg['text']) ?>.</p>
        <p class="mb-1"><strong>Trabajador:</strong> <?= Helpers::e($msg['worker']) ?></p>
        <p class="mb-0"><strong><?= Helpers::e($msg['document_label']) ?>:</strong> <?= Helpers::e($msg['document_number']) ?></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-success" data-bs-dismiss="modal">Aceptar</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
  (function() {
    const form = document.getElementById('attendanceForm');
    const button = document.getElementById('btnMark');
    const status = document.getElementById('locationStatus');
    const latitude = document.getElementById('latitude');
    const longitude = document.getElementById('longitude');
    const accuracy = document.getElementById('accuracy');
    const radius = <?= (int)$locationReference['radius_meters'] ?>;
    function reset(message) {
      latitude.value = longitude.value = accuracy.value = '';
      button.disabled = false;
      button.removeAttribute('aria-busy');
      document.getElementById('markSpinner').classList.add('d-none');
      document.getElementById('markButtonText').textContent = 'Reintentar marcación';
      status.className = 'alert alert-danger small';
      status.textContent = message;
    }
    form.addEventListener('submit', function(event) {
      event.preventDefault();
      if (button.disabled) return;
      latitude.value = longitude.value = accuracy.value = '';
      if (!window.isSecureContext || !navigator.geolocation) {
        reset('No se puede obtener tu ubicación. Abre esta página mediante HTTPS en un navegador con ubicación habilitada.');
        return;
      }
      button.disabled = true;
      button.setAttribute('aria-busy', 'true');
      document.getElementById('markSpinner').classList.remove('d-none');
      document.getElementById('markButtonText').textContent = 'Obteniendo ubicación…';
      status.className = 'alert alert-info small';
      status.textContent = 'Permite el acceso a tu ubicación. Estamos obteniendo una lectura nueva del GPS.';
      navigator.geolocation.getCurrentPosition(function(position) {
        const coords = position.coords;
        if (!Number.isFinite(coords.latitude) || !Number.isFinite(coords.longitude)
            || Math.abs(coords.latitude) > 90 || Math.abs(coords.longitude) > 180) {
          reset('No se pudo obtener una ubicación válida. Activa el GPS y vuelve a intentar.');
          return;
        }
        if (!Number.isFinite(coords.accuracy) || coords.accuracy <= 0 || coords.accuracy > radius) {
          reset('La precisión del GPS no es suficiente (se requiere ' + radius + ' m o menos). Activa la ubicación precisa y vuelve a intentar cerca del local.');
          return;
        }
        latitude.value = coords.latitude;
        longitude.value = coords.longitude;
        accuracy.value = coords.accuracy;
        document.getElementById('markButtonText').textContent = 'Validando marcación…';
        status.textContent = 'Ubicación obtenida. Comprobando la distancia a Terranova.';
        HTMLFormElement.prototype.submit.call(form);
      }, function(error) {
        const messages = {
          1: 'Debes permitir el acceso a tu ubicación. Habilita el permiso de ubicación precisa en tu navegador y vuelve a intentar.',
          2: 'No se pudo determinar tu ubicación. Activa el GPS y vuelve a intentar.',
          3: 'Se agotó el tiempo para obtener tu ubicación. Vuelve a intentar.'
        };
        reset(messages[error.code] || 'No se pudo obtener tu ubicación. Vuelve a intentar.');
      }, { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 });
    });
    document.addEventListener('DOMContentLoaded', function() {
      const modal = document.getElementById('modalMarkSuccess');
      if (modal) bootstrap.Modal.getOrCreateInstance(modal).show();
    });
  })();
</script>

<style>
  #modalPromo .modal-dialog {
    width: calc(100vw - 2rem);
    max-width: 1600px;
  }

  #modalPromo .modal-content {
    min-height: calc(100vh - 2rem);
  }

  .promotions-board {
    min-width: 1120px;
    table-layout: fixed;
  }

  .promotions-board th,
  .promotions-board td {
    width: 15.5%;
    min-width: 165px;
    vertical-align: top;
  }

  .promotions-board .shift-heading {
    width: 7%;
    min-width: 100px;
    vertical-align: middle;
  }

  .promotion-item {
    border: 1px solid #dee2e6;
    border-radius: .5rem;
    background: #fff;
    padding: .75rem;
    overflow-wrap: anywhere;
  }

  .promotion-item + .promotion-item {
    margin-top: .75rem;
  }

  .promotion-item__title {
    font-weight: 600;
    margin-bottom: .35rem;
  }

  .promotion-item__content {
    white-space: pre-wrap;
  }

  @media (max-width: 991.98px) {
    #modalPromo .modal-dialog {
      width: 100%;
      max-width: none;
      height: 100%;
      margin: 0;
    }

    #modalPromo .modal-content {
      min-height: 100%;
      border: 0;
      border-radius: 0;
    }
  }
</style>

<div class="modal fade" id="modalPromo" tabindex="-1" aria-labelledby="modalPromoTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="modalPromoTitle">Promociones de la semana</h5>
          <div class="text-muted small">Lunes a sabado, por turno</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div id="promoStatus" class="text-muted">Cargando promociones...</div>
        <div class="table-responsive d-none" id="promoBoardWrapper">
          <table class="table table-bordered promotions-board mb-0" aria-label="Promociones semanales">
            <thead class="table-light" id="promoBoardHead"></thead>
            <tbody id="promoBoardBody"></tbody>
          </table>
        </div>
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

    const status = document.getElementById('promoStatus');
    const wrapper = document.getElementById('promoBoardWrapper');
    const head = document.getElementById('promoBoardHead');
    const body = document.getElementById('promoBoardBody');

    function appendTextElement(parent, tag, className, text) {
      const element = document.createElement(tag);
      element.className = className;
      element.textContent = text;
      parent.appendChild(element);
    }

    function renderBoard(data) {
      head.replaceChildren();
      body.replaceChildren();

      const headerRow = document.createElement('tr');
      appendTextElement(headerRow, 'th', 'shift-heading text-center', 'Turno');

      data.days.forEach((day) => {
        appendTextElement(headerRow, 'th', 'text-center', day.label);
      });
      head.appendChild(headerRow);

      data.shifts.forEach((shift) => {
        const row = document.createElement('tr');
        appendTextElement(row, 'th', 'shift-heading text-center table-light', shift.label);

        data.days.forEach((day) => {
          const cell = document.createElement('td');
          const cellPromotions = data.promotions.filter((promo) =>
            promo.weekday === day.value && promo.shift === shift.value
          );

          if (cellPromotions.length === 0) {
            appendTextElement(cell, 'span', 'text-muted small', 'Sin promocion');
          } else {
            cellPromotions.forEach((promo) => {
              const item = document.createElement('article');
              item.className = 'promotion-item';

              if (promo.title) {
                appendTextElement(item, 'div', 'promotion-item__title', promo.title);
              }
              appendTextElement(item, 'div', 'promotion-item__content', promo.content || '');
              cell.appendChild(item);
            });
          }

          row.appendChild(cell);
        });

        body.appendChild(row);
      });

      status.classList.add('d-none');
      wrapper.classList.remove('d-none');
    }

    modal.addEventListener('show.bs.modal', async function(){
      status.textContent = 'Cargando promociones...';
      status.classList.remove('d-none');
      wrapper.classList.add('d-none');

      try {
        const res = await fetch('/promotions/weekly', { headers: { 'Accept': 'application/json' }});
        const data = await res.json();

        if (!res.ok || !data || !data.ok || !Array.isArray(data.days) ||
            !Array.isArray(data.shifts) || !Array.isArray(data.promotions)) {
          throw new Error('Respuesta invalida');
        }

        renderBoard(data);
      } catch (e) {
        status.textContent = 'No se pudieron cargar las promociones.';
      }
    });
  })();
</script>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
