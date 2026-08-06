<?php
require_once __DIR__ . '/../core/Database.php';

class JobFunction
{
  private static bool $schemaEnsured = false;

  public static function ensureSchema(): void
  {
    if (self::$schemaEnsured) return;
    Database::conn()->exec("CREATE TABLE IF NOT EXISTS job_functions (
      id INT NOT NULL AUTO_INCREMENT,
      area_id INT NOT NULL,
      title VARCHAR(180) NOT NULL,
      description TEXT NOT NULL,
      is_active TINYINT(1) NOT NULL DEFAULT 1,
      is_published TINYINT(1) NOT NULL DEFAULT 0,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY idx_job_functions_area (area_id),
      KEY idx_job_functions_visibility (is_active, is_published),
      CONSTRAINT fk_job_functions_area FOREIGN KEY (area_id) REFERENCES work_areas (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    self::$schemaEnsured = true;
  }

  public static function all(string $search = '', int $areaId = 0, string $status = '', string $publication = ''): array
  {
    self::ensureSchema();
    $sql = "SELECT jf.*, wa.name AS area_name FROM job_functions jf JOIN work_areas wa ON wa.id=jf.area_id WHERE 1=1";
    $params = [];
    if ($search !== '') { $sql .= " AND (jf.title LIKE ? OR jf.description LIKE ?)"; $params[] = "%{$search}%"; $params[] = "%{$search}%"; }
    if ($areaId > 0) { $sql .= " AND jf.area_id=?"; $params[] = $areaId; }
    if (in_array($status, ['active','inactive'], true)) { $sql .= " AND jf.is_active=?"; $params[] = $status === 'active' ? 1 : 0; }
    if (in_array($publication, ['published','hidden'], true)) { $sql .= " AND jf.is_published=?"; $params[] = $publication === 'published' ? 1 : 0; }
    $sql .= " ORDER BY wa.name ASC, jf.is_active DESC, jf.title ASC";
    $st = Database::conn()->prepare($sql); $st->execute($params); return $st->fetchAll();
  }

  public static function publishedForWorker(int $userId): array
  {
    self::ensureSchema();
    $st = Database::conn()->prepare("SELECT jf.id,jf.title,jf.description,jf.updated_at,wa.name AS area_name
      FROM users u JOIN work_areas wa ON wa.id=u.area_id
      JOIN job_functions jf ON jf.area_id=wa.id
      WHERE u.id=? AND u.role='worker' AND jf.is_active=1 AND jf.is_published=1
      ORDER BY jf.title ASC");
    $st->execute([$userId]); return $st->fetchAll();
  }

  public static function create(array $data): void
  {
    $p = self::payload($data); self::ensureSchema();
    $st = Database::conn()->prepare("INSERT INTO job_functions(area_id,title,description,is_active,is_published) VALUES(?,?,?,?,?)");
    $st->execute([$p['area_id'],$p['title'],$p['description'],$p['is_active'],$p['is_published']]);
  }

  public static function update(int $id, array $data): void
  {
    if ($id <= 0) throw new RuntimeException('Registro inválido.');
    $p = self::payload($data); self::ensureSchema();
    $st = Database::conn()->prepare("UPDATE job_functions SET area_id=?,title=?,description=?,is_active=?,is_published=? WHERE id=?");
    $st->execute([$p['area_id'],$p['title'],$p['description'],$p['is_active'],$p['is_published'],$id]);
  }

  public static function delete(int $id): void
  {
    self::ensureSchema(); $st=Database::conn()->prepare("DELETE FROM job_functions WHERE id=?"); $st->execute([$id]);
  }

  private static function payload(array $data): array
  {
    $areaId=(int)($data['area_id']??0); $title=trim((string)($data['title']??'')); $description=trim((string)($data['description']??''));
    if ($areaId<=0) throw new RuntimeException('Selecciona un área.');
    $st=Database::conn()->prepare("SELECT id FROM work_areas WHERE id=?"); $st->execute([$areaId]);
    if (!$st->fetch()) throw new RuntimeException('El área seleccionada no existe.');
    if ($title==='') throw new RuntimeException('El título es obligatorio.');
    if ($description==='') throw new RuntimeException('La descripción o funciones son obligatorias.');
    return ['area_id'=>$areaId,'title'=>$title,'description'=>$description,
      'is_active'=>(int)($data['is_active']??0)===1?1:0,
      'is_published'=>(int)($data['is_published']??0)===1?1:0];
  }
}
