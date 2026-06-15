<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../models/Promotion.php';

class PromotionsController extends Controller
{
  public function weekly(): void
  {
    header('Content-Type: application/json; charset=utf-8');

    $promotions = array_map(static function (array $promo): array {
      return [
        'id' => (int)$promo['id'],
        'weekday' => (int)$promo['weekday'],
        'shift' => (string)$promo['shift'],
        'title' => (string)($promo['title'] ?? ''),
        'content' => (string)($promo['content'] ?? ''),
      ];
    }, Promotion::activeSchedule());

    echo json_encode([
      'ok' => true,
      'days' => [
        ['value' => 1, 'label' => Promotion::weekdayLabel(1)],
        ['value' => 2, 'label' => Promotion::weekdayLabel(2)],
        ['value' => 3, 'label' => Promotion::weekdayLabel(3)],
        ['value' => 4, 'label' => Promotion::weekdayLabel(4)],
        ['value' => 5, 'label' => Promotion::weekdayLabel(5)],
        ['value' => 6, 'label' => Promotion::weekdayLabel(6)],
      ],
      'shifts' => [
        ['value' => 'morning', 'label' => Promotion::shiftLabel('morning')],
        ['value' => 'afternoon', 'label' => Promotion::shiftLabel('afternoon')],
      ],
      'promotions' => $promotions,
    ], JSON_UNESCAPED_UNICODE);
  }

  /**
   * Endpoint público para obtener la promoción del día.
   * Responde JSON.
   *
   * Query opcional:
   * - shift=morning|afternoon
   */
  public function today(): void
  {
    header('Content-Type: application/json; charset=utf-8');

    // 1=Lun ... 7=Dom
    $weekday = (int)date('N');

    // Solo Lunes a Sábado
    if ($weekday < 1 || $weekday > 6) {
      echo json_encode([
        'ok' => true,
        'hasPromo' => false,
        'message' => 'Hoy no hay promociones (solo Lun–Sáb).'
      ]);
      return;
    }

    $shift = trim((string)($_GET['shift'] ?? ''));
    if ($shift !== 'morning' && $shift !== 'afternoon') {
      // Determinar turno por hora local del servidor
      $hour = (int)date('G');
      // Heurística simple: mañana antes de 15:00, tarde desde 15:00
      $shift = ($hour < 15) ? 'morning' : 'afternoon';
    }

    $promo = Promotion::forDayAndShift($weekday, $shift);
    if (!$promo) {
      // Si no hay promo exacta del turno, devolvemos cualquiera del día
      $promo = Promotion::forDayAnyShift($weekday);
    }

    if (!$promo) {
      echo json_encode([
        'ok' => true,
        'hasPromo' => false,
        'weekday' => $weekday,
        'weekdayLabel' => Promotion::weekdayLabel($weekday),
        'shift' => $shift,
        'shiftLabel' => Promotion::shiftLabel($shift),
        'message' => 'No hay promoción configurada para hoy.'
      ]);
      return;
    }

    echo json_encode([
      'ok' => true,
      'hasPromo' => true,
      'weekday' => (int)$promo['weekday'],
      'weekdayLabel' => Promotion::weekdayLabel((int)$promo['weekday']),
      'shift' => (string)$promo['shift'],
      'shiftLabel' => Promotion::shiftLabel((string)$promo['shift']),
      'title' => (string)($promo['title'] ?? ''),
      'content' => (string)($promo['content'] ?? ''),
    ]);
  }
}
