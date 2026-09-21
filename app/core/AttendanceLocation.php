<?php

class AttendanceLocation
{
  /**
   * Validate raw browser values before any numeric conversion or database write.
   */
  public static function requireAllowed($latitude, $longitude, $accuracy, array $reference): array
  {
    $location = self::evaluate($latitude, $longitude, $reference);
    if ($location['distance'] === null) {
      throw new RuntimeException('Debes permitir el acceso a tu ubicación para marcar asistencia. Activa la ubicación y vuelve a intentar.');
    }
    if (!is_numeric($accuracy) || !is_finite((float)$accuracy) || (float)$accuracy <= 0
        || (float)$accuracy > $reference['max_accuracy_meters']) {
      throw new RuntimeException('No se pudo obtener una ubicación con precisión de ' . $reference['max_accuracy_meters'] . ' m. Activa la ubicación precisa y vuelve a intentar cerca del local.');
    }
    if ($location['distance'] > $reference['radius_meters']) {
      throw new RuntimeException('No se registró la asistencia: estás aproximadamente a '
        . number_format($location['distance'], 1, ',', '.') . ' m de Terranova. Debes estar dentro de '
        . $reference['radius_meters'] . ' m del punto de referencia.');
    }
    return [(float)$latitude, (float)$longitude];
  }
  public static function evaluate($latitude, $longitude, array $reference): array
  {
    if ($latitude === null || $longitude === null || $latitude === '' || $longitude === '') {
      return ['label' => 'Sin ubicación', 'color' => 'secondary', 'distance' => null, 'url' => null];
    }
    if (!is_numeric($latitude) || !is_numeric($longitude)
        || !is_finite((float)$latitude) || !is_finite((float)$longitude)
        || abs((float)$latitude) > 90 || abs((float)$longitude) > 180) {
      return ['label' => 'Ubicación inválida', 'color' => 'secondary', 'distance' => null, 'url' => null];
    }
    $lat = (float)$latitude;
    $lng = (float)$longitude;
    $deltaLat = deg2rad($lat - $reference['latitude']);
    $deltaLng = deg2rad($lng - $reference['longitude']);
    $a = sin($deltaLat / 2) ** 2
      + cos(deg2rad($reference['latitude'])) * cos(deg2rad($lat)) * sin($deltaLng / 2) ** 2;
    $distance = 6371000 * 2 * asin(sqrt(max(0, min(1, $a))));
    $matches = $distance <= $reference['radius_meters'];
    return [
      'label' => $matches ? 'Coincide' : 'Fuera del radio',
      'color' => $matches ? 'success' : 'danger',
      'distance' => $distance,
      'url' => 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($lat . ',' . $lng),
    ];
  }
}