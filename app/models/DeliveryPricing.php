<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/Product.php';

class DeliveryPricing
{
  private static bool $schemaEnsured = false;

  public static function ensureSchema(): void
  {
    if (self::$schemaEnsured) return;
    Product::ensureSchema();
    $pdo = Database::conn();
    $pdo->exec("CREATE TABLE IF NOT EXISTS delivery_platforms (
      id INT NOT NULL AUTO_INCREMENT, slug VARCHAR(40) NOT NULL, name VARCHAR(80) NOT NULL,
      commission_percent DECIMAL(5,2) NOT NULL DEFAULT 0, is_active TINYINT(1) NOT NULL DEFAULT 1,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY(id), UNIQUE KEY uq_delivery_platform_slug(slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS delivery_product_prices (
      product_id INT NOT NULL, platform_id INT NOT NULL, published_price DECIMAL(12,2) NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY(product_id,platform_id), KEY idx_delivery_prices_platform(platform_id),
      CONSTRAINT fk_delivery_price_product FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE ON UPDATE CASCADE,
      CONSTRAINT fk_delivery_price_platform FOREIGN KEY(platform_id) REFERENCES delivery_platforms(id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $seed = $pdo->prepare("INSERT INTO delivery_platforms(slug,name,commission_percent,is_active) VALUES(?,?,?,1)
      ON DUPLICATE KEY UPDATE name=VALUES(name),is_active=1");
    $seed->execute(['pedidosya','PedidosYa',30]);
    $seed->execute(['rappi','Rappi',25]);
    self::$schemaEnsured = true;
  }

  public static function platforms(): array
  {
    self::ensureSchema();
    return Database::conn()->query("SELECT * FROM delivery_platforms WHERE is_active=1 ORDER BY id")->fetchAll();
  }

  public static function updateCommissions(array $values): void
  {
    self::ensureSchema();
    $st = Database::conn()->prepare("UPDATE delivery_platforms SET commission_percent=? WHERE id=?");
    foreach (self::platforms() as $platform) {
      $raw = str_replace(',', '.', trim((string)($values[$platform['id']] ?? '')));
      if (!is_numeric($raw) || (float)$raw < 0 || (float)$raw >= 100) {
        throw new RuntimeException('La comisión de ' . $platform['name'] . ' debe estar entre 0 y 99.99%.');
      }
      $st->execute([round((float)$raw,2),(int)$platform['id']]);
    }
  }

  public static function products(string $search='', int $categoryId=0): array
  {
    self::ensureSchema();
    $platforms = self::platforms();
    $sql = "SELECT p.id,p.name,p.variant,p.unit_price,pc.name category_name
      FROM products p LEFT JOIN product_categories pc ON pc.id=p.category_id WHERE p.is_active=1";
    $params=[];
    if ($categoryId>0) { $sql.=" AND p.category_id=?"; $params[]=$categoryId; }
    if ($search!=='') { $sql.=" AND (p.name LIKE ? OR pc.name LIKE ?)"; $params[]='%'.$search.'%'; $params[]='%'.$search.'%'; }
    $sql.=" ORDER BY COALESCE(pc.name,'Sin categoría'),p.name";
    $st=Database::conn()->prepare($sql); $st->execute($params); $products=$st->fetchAll();
    $priceSt=Database::conn()->prepare("SELECT platform_id,published_price FROM delivery_product_prices WHERE product_id=?");
    foreach ($products as &$product) {
      $priceSt->execute([(int)$product['id']]); $saved=[];
      foreach($priceSt->fetchAll() as $row) $saved[(int)$row['platform_id']]=$row['published_price'];
      $product['platforms']=[];
      foreach($platforms as $platform) {
        $commission=(float)$platform['commission_percent']; $card=$product['unit_price']!==null?(float)$product['unit_price']:null;
        $suggested=$card!==null?round($card/(1-$commission/100),2):null;
        $published=array_key_exists((int)$platform['id'],$saved)&&$saved[(int)$platform['id']]!==null?(float)$saved[(int)$platform['id']]:null;
        $effective=$published??$suggested;
        $product['platforms'][]=$platform+['suggested_price'=>$suggested,'published_price'=>$published,'effective_price'=>$effective,
          'commission_amount'=>$effective!==null?round($effective*$commission/100,2):null,
          'net_received'=>$effective!==null?round($effective*(1-$commission/100),2):null];
      }
    }
    unset($product); return $products;
  }

  public static function saveProductPrices(int $productId,array $prices): void
  {
    self::ensureSchema();
    if ($productId<=0) throw new RuntimeException('Producto inválido.');
    $st=Database::conn()->prepare("INSERT INTO delivery_product_prices(product_id,platform_id,published_price) VALUES(?,?,?)
      ON DUPLICATE KEY UPDATE published_price=VALUES(published_price)");
    $saved = 0;
    foreach(self::platforms() as $platform) {
      if (!array_key_exists($platform['id'], $prices)) continue;
      $raw=str_replace(',','.',trim((string)$prices[$platform['id']]));
      if ($raw==='' || !is_numeric($raw) || (float)$raw<0) throw new RuntimeException('Ingresa un precio válido para '.$platform['name'].'.');
      $st->execute([$productId,(int)$platform['id'],round((float)$raw,2)]);
      $saved++;
    }
    if ($saved === 0) throw new RuntimeException('No se recibió ningún precio para guardar.');
  }
}
