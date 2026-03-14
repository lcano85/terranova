<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../models/Promotion.php';

class PromotionsController extends Controller
{
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
