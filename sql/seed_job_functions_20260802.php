<?php
require_once __DIR__ . '/../app/models/JobFunction.php';

JobFunction::ensureSchema();
$pdo = Database::conn();

$records = [
  'Campo' => [
    'title' => 'Funciones del personal de Campo / Volantero',
    'description' => "1. Realizar volanteo en las zonas y horarios indicados por el responsable.\n2. Invitar al público a conocer el local y comunicar de forma clara las promociones vigentes.\n3. Ofrecer degustaciones respetando las indicaciones de presentación, higiene y cantidad.\n4. Mantener una actitud amable, activa y respetuosa; evitar discusiones o insistencia excesiva.\n5. Conocer la carta, horarios, ubicación y promociones para responder consultas básicas.\n6. Cuidar y controlar volantes, muestras, bandejas y demás materiales entregados.\n7. Mantener limpio y ordenado el punto de promoción y recoger los residuos generados.\n8. Informar los resultados de la jornada, consultas frecuentes y comentarios recibidos.\n9. Reportar inmediatamente incidentes, accidentes, reclamos o situaciones de riesgo.\n10. Marcar asistencia al ingresar y al salir, y comunicar con anticipación tardanzas, ausencias o cualquier situación personal que afecte el turno."
  ],
  'Barra' => [
    'title' => 'Funciones del personal de Barra',
    'description' => "1. Preparar tragos, bebidas, waffles y crepes siguiendo las recetas, porciones y presentación establecidas.\n2. Despachar cervezas, gaseosas y demás bebidas verificando que el pedido esté completo y correcto.\n3. Atender las comandas según el orden de ingreso y coordinar oportunamente con Salón y Cocina.\n4. Revisar al inicio y cierre del turno el inventario de bebidas, insumos, frutas, utensilios y envases.\n5. Registrar e informar faltantes, mermas, roturas, productos vencidos o diferencias de inventario.\n6. Aplicar rotación de productos y conservar correctamente los insumos abiertos o refrigerados.\n7. Mantener limpia y desinfectada la barra, equipos, licuadoras, utensilios, superficies y pisos.\n8. Verificar que equipos, refrigeradores y conexiones queden operativos y seguros.\n9. Reponer oportunamente hielo, vasos, servilletas y materiales necesarios para el servicio.\n10. Reportar inmediatamente incidentes, accidentes, reclamos, fallas o situaciones de riesgo.\n11. Marcar asistencia al ingresar y al salir, y comunicar con anticipación tardanzas, ausencias o cualquier situación personal que afecte el turno."
  ],
  'Cocina' => [
    'title' => 'Funciones del personal de Cocina',
    'description' => "1. Preparar los platos respetando recetas, porciones, tiempos, calidad y presentación establecidos.\n2. Revisar cada comanda y entregar los platos completos, calientes y correctamente identificados.\n3. Coordinar con Salón y Barra los tiempos de entrega, cambios, faltantes y observaciones del pedido.\n4. Revisar el inventario de insumos al inicio y cierre del turno e informar oportunamente las necesidades de reposición.\n5. Registrar e informar faltantes, mermas, productos vencidos, deteriorados o diferencias de inventario.\n6. Aplicar rotación, etiquetado, conservación y almacenamiento adecuado de los alimentos.\n7. Mantener limpia y desinfectada su área, mesas, utensilios, equipos, cámaras, cocina y pisos.\n8. Cumplir las normas de higiene personal, lavado de manos y prevención de contaminación cruzada.\n9. Verificar el apagado y cierre seguro de equipos, gas, agua y conexiones al finalizar el turno.\n10. Reportar inmediatamente incidentes, accidentes, reclamos, fallas o situaciones de riesgo.\n11. Marcar asistencia al ingresar y al salir, y comunicar con anticipación tardanzas, ausencias o cualquier situación personal que afecte el turno."
  ],
  'Salón' => [
    'title' => 'Funciones del personal de Salón',
    'description' => "1. Ordenar, limpiar y preparar el salón antes, durante y después del servicio.\n2. Verificar que mesas, sillas, cartas y zonas de atención estén limpias y correctamente ubicadas.\n3. Mantener listos y abastecidos servilletas, cubiertos, utensilios, envases y materiales de atención.\n4. Revisar el inventario asignado al salón e informar faltantes, roturas, pérdidas o necesidades de reposición.\n5. Recibir a cada cliente con respeto, saludar, presentarse y brindar orientación sobre la carta y promociones.\n6. Tomar y confirmar correctamente los pedidos, incluyendo observaciones, cambios o restricciones indicadas.\n7. Coordinar con Cocina y Barra, entregar los pedidos completos y verificar la conformidad del cliente.\n8. Atender consultas y reclamos con calma; solicitar apoyo del responsable cuando corresponda.\n9. Apoyar en caja según la autorización recibida y verificar cobros, comprobantes y medios de pago.\n10. Revisar, embalar y despachar pedidos de delivery y para llevar, comprobando productos, cantidades, complementos y datos del pedido.\n11. Mantener despejadas y seguras las zonas de tránsito y reportar daños, accidentes o situaciones de riesgo.\n12. Marcar asistencia al ingresar y al salir, y comunicar con anticipación tardanzas, ausencias o cualquier situación personal que afecte el turno."
  ],
];

$areaSt = $pdo->prepare('SELECT id FROM work_areas WHERE name=? LIMIT 1');
$findSt = $pdo->prepare('SELECT id FROM job_functions WHERE area_id=? AND title=? LIMIT 1');
$insertSt = $pdo->prepare('INSERT INTO job_functions(area_id,title,description,is_active,is_published) VALUES(?,?,?,1,1)');
$updateSt = $pdo->prepare('UPDATE job_functions SET description=?,is_active=1,is_published=1 WHERE id=?');

$pdo->beginTransaction();
try {
  foreach ($records as $areaName => $record) {
    $areaSt->execute([$areaName]);
    $areaId = (int)$areaSt->fetchColumn();
    if ($areaId <= 0) throw new RuntimeException("No existe el área {$areaName}.");
    $findSt->execute([$areaId, $record['title']]);
    $id = (int)$findSt->fetchColumn();
    if ($id > 0) $updateSt->execute([$record['description'], $id]);
    else $insertSt->execute([$areaId, $record['title'], $record['description']]);
    echo "OK: {$areaName}\n";
  }
  $pdo->commit();
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  throw $e;
}
