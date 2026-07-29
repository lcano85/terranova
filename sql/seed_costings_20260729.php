<?php
/**
 * Carga inicial de escandallos Terranova (29-07-2026).
 * Precios de referencia conservadores de Tottus, Metro, Plaza Vea y precios
 * ya registrados en Insumos. Ejecutable e idempotente: actualiza por receta.
 */
require_once __DIR__ . '/../app/models/Costing.php';
require_once __DIR__ . '/../app/models/Recipe.php';
require_once __DIR__ . '/../app/models/Supply.php';
require_once __DIR__ . '/../app/models/Product.php';

$catalog = [
  // nombre => [costo compra, cantidad, unidad, merma %, presentación, área]
  'Aceite vegetal' => [4.99,900,'ml',0,'Botella 900 ml',3],
  'Agua' => [3.50,2500,'ml',0,'Bidón/botella 2.5 L',3],
  'Ají amarillo' => [7.50,1000,'g',12,'A granel 1 kg',1],
  'Ají limo' => [12.00,1000,'g',12,'A granel 1 kg',1],
  'Ajinomoto' => [9.50,500,'g',0,'Bolsa 500 g',2],
  'Ajo' => [12.00,1000,'g',15,'A granel 1 kg',1],
  'Apio' => [4.50,1000,'g',25,'Atado / kg',1],
  'Azúcar blanca' => [4.50,1000,'g',0,'Bolsa 1 kg',2],
  'Carne molida de res' => [26.90,1000,'g',5,'Carne molida 1 kg',3],
  'Carne para costilla' => [38.90,1000,'g',18,'Costilla de res 1 kg',3],
  'Cebolla blanca' => [4.50,1000,'g',12,'A granel 1 kg',1],
  'Cecina' => [42.00,1000,'g',5,'Proveedor 1 kg',4],
  'Chimichurri' => [8.90,250,'g',0,'Frasco 250 g',3],
  'Chorizo amazónico' => [18.00,400,'g',5,'Paquete 400 g',4],
  'Chorizo parrillero' => [15.90,400,'g',5,'Paquete 400 g',3],
  'Chuño' => [8.50,500,'g',0,'Bolsa 500 g',2],
  'Cocona' => [8.00,1000,'g',20,'A granel 1 kg',1],
  'Culantro' => [2.00,100,'g',25,'Atado aprox. 100 g',1],
  'Hondashi' => [18.90,100,'g',0,'Bolsa 100 g',3],
  'Hot dog de pollo' => [10.00,440,'g',0,'Paquete 440 g',3],
  'Hot dog vienesa' => [16.50,500,'g',0,'Paquete 500 g',3],
  'Huevo' => [18.00,30,'und',0,'Plancha 30 unidades',1],
  'Jamón inglés' => [58.90,1000,'g',3,'Venta por kg',3],
  'Jugo de maracuyá' => [12.00,1000,'ml',8,'Pulpa/jugo 1 L',4],
  'Ketchup' => [6.90,380,'g',0,'Doypack 380 g',3],
  'Kion' => [10.00,1000,'g',15,'A granel 1 kg',1],
  'Lechuga' => [4.50,500,'g',25,'Cabeza aprox. 500 g',1],
  'Limón' => [5.50,1000,'g',45,'A granel 1 kg',1],
  'Louisiana hot sauce' => [26.90,355,'ml',0,'Botella 355 ml',3],
  'Maicena' => [11.90,500,'g',0,'Caja 500 g',3],
  'Mantequilla' => [12.90,200,'g',0,'Barra 200 g',3],
  'Masa wantán' => [8.50,500,'g',2,'Paquete 500 g',4],
  'Mayonesa' => [17.60,850,'g',0,'Doypack 850 g',3],
  'Miel' => [15.90,500,'g',0,'Frasco 500 g',3],
  'Mostaza' => [5.90,400,'g',0,'Doypack 400 g',3],
  'Nuggets de pollo' => [16.90,500,'g',0,'Bolsa 500 g',3],
  'Papa amarilla' => [7.49,1000,'g',18,'A granel 1 kg',3],
  'Pechuga de pollo' => [19.90,1000,'g',8,'Filete 1 kg',3],
  'Pimienta' => [8.90,50,'g',0,'Frasco 50 g',3],
  'Pimiento rojo' => [9.00,1000,'g',18,'A granel 1 kg',1],
  'Piña' => [5.50,1000,'g',40,'A granel 1 kg',1],
  'Plátano para freír' => [5.50,1000,'g',30,'A granel 1 kg',1],
  'Queso dambo/edam' => [55.00,1000,'g',2,'Venta por kg',3],
  'Rocoto' => [8.00,1000,'g',15,'A granel 1 kg',1],
  'Sal' => [2.50,1000,'g',0,'Bolsa 1 kg',2],
  'Salsa acevichada preparada' => [12.00,1000,'g',0,'Subreceta 1 kg',4],
  'Salsa inglesa' => [9.90,500,'ml',0,'Botella 500 ml',3],
  'Sibarita roja' => [2.50,20,'g',0,'Sobre 20 g',3],
  'Sillao' => [5.90,500,'ml',0,'Botella 500 ml',3],
  'Tocino ahumado' => [15.90,200,'g',5,'Paquete 200 g',3],
  'Tomate' => [6.29,1000,'g',10,'A granel 1 kg',3],
  'Tortilla de trigo' => [7.50,8,'und',0,'Paquete 8 unidades',3],
  'Vinagre' => [3.30,500,'ml',0,'Botella 500 ml',3],
];

// Atajo: [insumo, cantidad usada, unidad, descripción original opcional].
$r = [
  1 => [['Masa wantán',1500,'g'],['Jamón inglés',500,'g'],['Queso dambo/edam',750,'g'],['Huevo',2,'und']],
  2 => [['Masa wantán',1500,'g'],['Jamón inglés',500,'g'],['Queso dambo/edam',500,'g'],['Tocino ahumado',500,'g'],['Huevo',2,'und']],
  3 => [['Masa wantán',1500,'g'],['Chorizo amazónico',500,'g'],['Cecina',500,'g'],['Plátano para freír',800,'g'],['Huevo',3,'und']],
  4 => [['Masa wantán',1500,'g'],['Jamón inglés',700,'g'],['Queso dambo/edam',700,'g'],['Huevo',3,'und']],
  5 => [['Pechuga de pollo',250,'g'],['Mostaza',30,'g'],['Huevo',1,'und'],['Sal',1.5,'g'],['Ajinomoto',1.5,'g'],['Vinagre',15,'ml'],['Chuño',180,'g'],['Aceite vegetal',30,'ml']],
  6 => [['Papa amarilla',250,'g'],['Hot dog de pollo',100,'g'],['Hot dog vienesa',100,'g'],['Aceite vegetal',35,'ml']],
  7 => [['Pechuga de pollo',280,'g'],['Papa amarilla',250,'g'],['Hot dog de pollo',100,'g'],['Hot dog vienesa',100,'g'],['Aceite vegetal',45,'ml']],
  8 => [['Papa amarilla',250,'g'],['Hot dog de pollo',100,'g'],['Hot dog vienesa',100,'g'],['Carne molida de res',100,'g'],['Huevo',1,'und'],['Aceite vegetal',40,'ml']],
  9 => [['Papa amarilla',250,'g'],['Hot dog de pollo',50,'g'],['Hot dog vienesa',50,'g'],['Chorizo amazónico',200,'g'],['Plátano para freír',120,'g'],['Aceite vegetal',40,'ml']],
  10 => [['Papa amarilla',250,'g'],['Hot dog de pollo',100,'g'],['Hot dog vienesa',100,'g'],['Huevo',2,'und'],['Aceite vegetal',40,'ml']],
  11 => [['Pan de hamburguesa',1,'und'],['Lechuga',25,'g'],['Tomate',90,'g'],['Queso dambo/edam',40,'g'],['Papa amarilla',250,'g'],['Mayonesa',20,'g'],['Carne molida de res',150,'g'],['Aceite vegetal',35,'ml']],
  12 => [['Pan de hamburguesa',1,'und'],['Lechuga',25,'g'],['Tomate',90,'g'],['Queso dambo/edam',40,'g'],['Carne molida de res',150,'g'],['Jamón inglés',40,'g'],['Piña',80,'g'],['Papa amarilla',225,'g'],['Mayonesa',20,'g'],['Mantequilla',3,'g'],['Aceite vegetal',35,'ml']],
  13 => [['Pan de hamburguesa',1,'und'],['Lechuga',25,'g'],['Tomate',90,'g'],['Mayonesa',20,'g'],['Carne molida de res',150,'g'],['Queso dambo/edam',40,'g'],['Chorizo parrillero',100,'g'],['Tocino ahumado',30,'g'],['Chimichurri',15,'g'],['Papa amarilla',225,'g'],['Aceite vegetal',35,'ml']],
  14 => [['Pan de hamburguesa',1,'und'],['Lechuga',25,'g'],['Tomate',90,'g'],['Carne molida de res',150,'g'],['Queso dambo/edam',40,'g'],['Mayonesa',20,'g'],['Plátano para freír',120,'g'],['Huevo',1,'und'],['Papa amarilla',225,'g'],['Aceite vegetal',40,'ml']],
  15 => [['Mini pan de hamburguesa',3,'und'],['Lechuga',40,'g'],['Tomate',90,'g'],['Queso dambo/edam',40,'g'],['Tocino ahumado',30,'g'],['Carne molida de res',150,'g'],['Mayonesa',25,'g'],['Papa amarilla',225,'g'],['Aceite vegetal',35,'ml']],
  16 => [['Nuggets de pollo',150,'g'],['Papa amarilla',200,'g'],['Aceite vegetal',35,'ml']],
  17 => [['Tortilla de trigo',1,'und'],['Lechuga',40,'g'],['Tomate',90,'g'],['Queso dambo/edam',20,'g'],['Pechuga de pollo',200,'g'],['Papa amarilla',200,'g'],['Mayonesa',20,'g'],['Aceite vegetal',30,'ml']],
  18 => [['Tortilla de trigo',1,'und'],['Lechuga',25,'g'],['Tomate',90,'g'],['Queso dambo/edam',20,'g'],['Chorizo parrillero',100,'g'],['Pechuga de pollo',200,'g'],['Mayonesa',20,'g']],
  19 => [['Tortilla de trigo',1,'und'],['Lechuga',25,'g'],['Tomate',90,'g'],['Queso dambo/edam',20,'g'],['Chorizo parrillero',200,'g'],['Mayonesa',20,'g']],
  20 => [['Tortilla de trigo',1,'und'],['Lechuga',25,'g'],['Tomate',90,'g'],['Pechuga de pollo',200,'g'],['Tocino ahumado',30,'g'],['Chorizo parrillero',100,'g'],['Queso dambo/edam',20,'g'],['Mayonesa',20,'g']],
  21 => [['Carne para costilla',1000,'g'],['Apio',200,'g'],['Pimiento rojo',600,'g'],['Cebolla blanca',600,'g'],['Ajo',125,'g'],['Tomate',600,'g'],['Sal',45,'g'],['Pimienta',10,'g'],['Ajinomoto',30,'g'],['Sibarita roja',20,'g'],['Vinagre',300,'ml'],['Sillao',200,'ml'],['Salsa inglesa',200,'ml'],['Ketchup',400,'g'],['Azúcar blanca',400,'g'],['Maicena',400,'g']],
  22 => [['Jugo de maracuyá',300,'ml'],['Maicena',100,'g'],['Agua',200,'ml'],['Miel',40,'g'],['Azúcar blanca',180,'g']],
  23 => [['Louisiana hot sauce',355,'ml'],['Mantequilla',100,'g'],['Sal',5,'g'],['Ajinomoto',5,'g']],
  24 => [['Cebolla blanca',150,'g'],['Cocona',200,'g'],['Ají limo',15,'g'],['Culantro',12,'g'],['Sal',5,'g'],['Ajinomoto',5,'g'],['Limón',240,'g']],
  25 => [['Aceite vegetal',250,'ml'],['Mostaza',100,'g'],['Huevo',1,'und'],['Ajo',5,'g'],['Agua',100,'ml'],['Maicena',70,'g'],['Ají limo',45,'g'],['Kion',3,'g'],['Apio',10,'g'],['Culantro',10,'g'],['Hondashi',5,'g'],['Limón',180,'g'],['Sal',5,'g'],['Ajinomoto',5,'g']],
  26 => [['Jugo de maracuyá',300,'ml'],['Azúcar blanca',100,'g'],['Miel',40,'g'],['Rocoto',60,'g'],['Agua',150,'ml'],['Maicena',100,'g']],
  27 => [['Salsa acevichada preparada',100,'g'],['Ají amarillo',120,'g']],
  28 => [['Rocoto',360,'g'],['Ajo',5,'g'],['Huevo',1,'und'],['Mostaza',100,'g'],['Aceite vegetal',250,'ml'],['Sal',7.5,'g'],['Ajinomoto',7.5,'g'],['Agua',100,'ml'],['Cebolla blanca',300,'g']],
];

// Elementos que no estaban en el catálogo principal de insumos.
$catalog += [
  'Pan de hamburguesa' => [8.50,8,'und',0,'Bolsa 8 unidades',3],
  'Mini pan de hamburguesa' => [9.90,12,'und',0,'Bolsa 12 unidades',3],
];

$productMap = [
  1=>80,2=>77,3=>78,4=>80,5=>60,6=>73,7=>70,8=>71,9=>8,10=>72,
  11=>69,12=>68,13=>67,14=>66,15=>65,16=>58,17=>64,18=>63,19=>62,20=>61,
];
$portions = [1=>12,2=>12,3=>12,4=>12,21=>40,22=>20,23=>15,24=>12,25=>20,26=>20,27=>10,28=>20];
$laborMinutes = [1=>45,2=>55,3=>60,4=>50,21=>120,22=>20,23=>10,24=>20,25=>25,26=>25,27=>15,28=>25];
$pdo = Database::conn();

function normSupply(string $name): string {
  $clean=preg_replace('/\s+/u',' ',trim($name)) ?: trim($name);
  return function_exists('mb_strtolower') ? mb_strtolower($clean,'UTF-8') : strtolower($clean);
}

// Crear faltantes y actualizar precio de referencia. Conserva las áreas adicionales.
$supplyIds = [];
foreach ($catalog as $name => [$cost,$qty,$unit,$waste,$package,$area]) {
  $normalized=normSupply($name);
  $st=$pdo->prepare("SELECT id FROM supplies WHERE normalized_name=? LIMIT 1");
  $st->execute([$normalized]);
  $id=(int)$st->fetchColumn();
  if (!$id) {
    $st=$pdo->prepare("INSERT INTO supplies(purchase_area_id,name,normalized_name,price,is_active) VALUES(?,?,?,?,1)");
    $st->execute([$area,$name,$normalized,$cost]);
    $id=(int)$pdo->lastInsertId();
  } else {
    $pdo->prepare("UPDATE supplies SET price=?,is_active=1,updated_at=NOW() WHERE id=?")->execute([$cost,$id]);
  }
  $pdo->prepare("INSERT IGNORE INTO supply_purchase_areas(supply_id,purchase_area_id) VALUES(?,?)")->execute([$id,$area]);
  $supplyIds[$name]=$id;
}

$recipesById=[];
foreach (Recipe::allForAdmin(null,'approved') as $recipe) $recipesById[(int)$recipe['id']]=$recipe;
$productsById=[];
foreach (Product::activeList() as $product) $productsById[(int)$product['id']]=$product;

$created=0; $updated=0;
foreach ($r as $recipeId => $lines) {
  if (!isset($recipesById[$recipeId])) throw new RuntimeException("Falta receta {$recipeId}");
  $items=['ingredient_name'=>[],'supply_id'=>[],'package_description'=>[],'purchase_cost'=>[],
    'purchase_quantity'=>[],'purchase_unit'=>[],'waste_percent'=>[],'usage_quantity'=>[],'usage_unit'=>[]];
  foreach ($lines as [$name,$usage,$usageUnit]) {
    if (!isset($catalog[$name],$supplyIds[$name])) throw new RuntimeException("Falta insumo {$name}");
    [$cost,$qty,$purchaseUnit,$waste,$package]=$catalog[$name];
    $items['ingredient_name'][]=$name;
    $items['supply_id'][]=$supplyIds[$name];
    $items['package_description'][]=$package;
    $items['purchase_cost'][]=$cost;
    $items['purchase_quantity'][]=$qty;
    $items['purchase_unit'][]=$purchaseUnit;
    $items['waste_percent'][]=$waste;
    $items['usage_quantity'][]=$usage;
    $items['usage_unit'][]=$usageUnit;
  }
  $productId=$productMap[$recipeId] ?? null;
  $sellingPrice=$productId && isset($productsById[$productId]) ? (float)$productsById[$productId]['unit_price'] : 0;
  $isBatch=$recipeId<=4 || $recipeId>=21;
  $payload=[
    'recipe_id'=>$recipeId,'product_id'=>$productId,'title'=>$recipesById[$recipeId]['title'],
    'portions'=>$portions[$recipeId] ?? 1,'items'=>$items,
    'labor_minutes'=>$laborMinutes[$recipeId] ?? ($isBatch?30:12),'labor_hourly_cost'=>8.50,
    'gas_cost'=>$isBatch?1.50:0.45,'equipment_cost'=>$isBatch?0.75:0.25,'other_cost'=>$isBatch?0.50:0.20,
    'selling_price'=>$sellingPrice,'target_margin'=>40,
    'notes'=>'Carga inicial 29/07/2026. Precios web Perú + insumos Terranova. Cantidades no explícitas estimadas; revisar con cocina.',
  ];
  $st=$pdo->prepare("SELECT id FROM costings WHERE recipe_id=? ORDER BY id LIMIT 1");
  $st->execute([$recipeId]);
  $existing=(int)$st->fetchColumn();
  if ($existing) { Costing::update($existing,$payload); $updated++; }
  else { Costing::create($payload); $created++; }
}
echo "Costeos creados={$created}; actualizados={$updated}; insumos catalogados=".count($catalog).PHP_EOL;
