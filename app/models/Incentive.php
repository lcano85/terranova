<?php
require_once __DIR__ . '/../core/Database.php';

class Incentive
{
  private static bool $schemaEnsured = false;

  public static function ensureSchema(): void
  {
    if (self::$schemaEnsured) return;
    Database::conn()->exec("CREATE TABLE IF NOT EXISTS incentives (
      id INT NOT NULL AUTO_INCREMENT,
      title VARCHAR(180) NOT NULL,
      amount DECIMAL(12,2) NOT NULL DEFAULT 0,
      description TEXT NULL,
      worker_message TEXT NULL,
      is_active TINYINT(1) NOT NULL DEFAULT 1,
      is_published TINYINT(1) NOT NULL DEFAULT 0,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY(id), KEY idx_incentives_active(is_active), KEY idx_incentives_published(is_published)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $column = Database::conn()->query("SHOW COLUMNS FROM incentives LIKE 'worker_message'")->fetch();
    if (!$column) {
      Database::conn()->exec("ALTER TABLE incentives ADD COLUMN worker_message TEXT NULL AFTER description");
    }
    self::$schemaEnsured = true;
  }

  public static function all(string $search='', string $status='', string $publication=''): array
  {
    self::ensureSchema();
    $sql="SELECT * FROM incentives WHERE 1=1"; $params=[];
    if($search!==''){
      $sql.=" AND (title LIKE ? OR description LIKE ? OR worker_message LIKE ?)";
      $params[]='%'.$search.'%';$params[]='%'.$search.'%';$params[]='%'.$search.'%';
    }
    if(in_array($status,['active','inactive'],true)){ $sql.=" AND is_active=?";$params[]=$status==='active'?1:0; }
    if(in_array($publication,['published','hidden'],true)){ $sql.=" AND is_published=?";$params[]=$publication==='published'?1:0; }
    $sql.=" ORDER BY is_published DESC,is_active DESC,updated_at DESC,id DESC";
    $st=Database::conn()->prepare($sql);$st->execute($params);return $st->fetchAll();
  }

  public static function publishedForWorkers(): array
  {
    self::ensureSchema();
    return Database::conn()->query("SELECT id,title,amount,worker_message,is_active,created_at,updated_at FROM incentives WHERE is_published=1 ORDER BY is_active DESC,updated_at DESC,id DESC")->fetchAll();
  }

  public static function create(array $data): void
  {
    self::ensureSchema();$p=self::payload($data);
    $st=Database::conn()->prepare("INSERT INTO incentives(title,amount,description,worker_message,is_active,is_published) VALUES(?,?,?,?,?,?)");
    $st->execute([$p['title'],$p['amount'],$p['description'],$p['worker_message'],$p['is_active'],$p['is_published']]);
  }

  public static function update(int $id,array $data): void
  {
    self::ensureSchema();if($id<=0)throw new RuntimeException('Incentivo inválido.');$p=self::payload($data);
    $st=Database::conn()->prepare("UPDATE incentives SET title=?,amount=?,description=?,worker_message=?,is_active=?,is_published=? WHERE id=?");
    $st->execute([$p['title'],$p['amount'],$p['description'],$p['worker_message'],$p['is_active'],$p['is_published'],$id]);
    if($st->rowCount()===0&&!self::find($id))throw new RuntimeException('El incentivo no existe.');
  }

  public static function delete(int $id): void
  {
    self::ensureSchema();$st=Database::conn()->prepare("DELETE FROM incentives WHERE id=?");$st->execute([$id]);
    if($st->rowCount()===0)throw new RuntimeException('El incentivo no existe.');
  }

  public static function find(int $id): ?array
  {
    self::ensureSchema();$st=Database::conn()->prepare("SELECT * FROM incentives WHERE id=?");$st->execute([$id]);return $st->fetch()?:null;
  }

  private static function payload(array $data): array
  {
    $title=preg_replace('/\s+/u',' ',trim((string)($data['title']??'')))?:'';
    $description=trim((string)($data['description']??''));
    $workerMessage=trim((string)($data['worker_message']??''));
    $raw=str_replace(',','.',trim((string)($data['amount']??'')));
    if($title==='')throw new RuntimeException('El título es obligatorio.');
    if(!is_numeric($raw)||(float)$raw<0)throw new RuntimeException('El monto debe ser un número mayor o igual a cero.');
    return ['title'=>$title,'amount'=>round((float)$raw,2),'description'=>$description!==''?$description:null,
      'worker_message'=>$workerMessage!==''?$workerMessage:null,
      'is_active'=>(int)($data['is_active']??0)===1?1:0,'is_published'=>(int)($data['is_published']??0)===1?1:0];
  }
}
