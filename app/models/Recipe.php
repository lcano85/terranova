<?php
require_once __DIR__ . '/../core/Database.php';

class Recipe
{
  private static bool $schemaEnsured = false;

  public static function ensureSchema(): void
  {
    if (self::$schemaEnsured) {
      return;
    }

    $pdo = Database::conn();
    $pdo->exec("
      CREATE TABLE IF NOT EXISTS recipes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        area_type VARCHAR(20) NOT NULL,
        title VARCHAR(180) NOT NULL,
        preparation TEXT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        approved_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_recipes_area_status (area_type, status),
        KEY idx_recipes_user (user_id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
      CREATE TABLE IF NOT EXISTS recipe_ingredients (
        id INT AUTO_INCREMENT PRIMARY KEY,
        recipe_id INT NOT NULL,
        ingredient TEXT NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        KEY idx_recipe_ingredients_recipe (recipe_id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    self::$schemaEnsured = true;
  }

  public static function areaTypeFromName(?string $areaName): ?string
  {
    $name = trim((string)$areaName);
    if ($name === '') {
      return null;
    }

    $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
    $normalized = strtolower($normalized !== false ? $normalized : $name);

    if (str_contains($normalized, 'cocina')) {
      return 'cocina';
    }

    if (str_contains($normalized, 'barra')) {
      return 'barra';
    }

    return null;
  }

  public static function canWorkerUse(?array $user): bool
  {
    return self::areaTypeFromName($user['area_name'] ?? null) !== null;
  }

  public static function sanitizeIngredients(array $ingredients): array
  {
    $clean = [];
    foreach ($ingredients as $ingredient) {
      $value = preg_replace('/\s+/u', ' ', trim((string)$ingredient)) ?? trim((string)$ingredient);
      if ($value !== '') {
        $clean[] = $value;
      }
    }

    return $clean;
  }

  public static function create(int $userId, string $areaType, string $title, array $ingredients, string $preparation): int
  {
    self::ensureSchema();
    $title = trim($title);
    $preparation = trim($preparation);
    $ingredients = self::sanitizeIngredients($ingredients);

    if (!in_array($areaType, ['cocina', 'barra'], true)) {
      throw new RuntimeException('Area no autorizada para registrar recetas.');
    }
    if ($title === '') {
      throw new RuntimeException('El titulo del producto es obligatorio.');
    }
    if (empty($ingredients)) {
      throw new RuntimeException('Debes agregar al menos un ingrediente.');
    }
    if ($preparation === '') {
      throw new RuntimeException('La preparacion es obligatoria.');
    }

    $pdo = Database::conn();
    $pdo->beginTransaction();

    try {
      $st = $pdo->prepare("
        INSERT INTO recipes (user_id, area_type, title, preparation, status)
        VALUES (?, ?, ?, ?, 'pending')
      ");
      $st->execute([$userId, $areaType, $title, $preparation]);
      $recipeId = (int)$pdo->lastInsertId();

      $ingredientSt = $pdo->prepare("
        INSERT INTO recipe_ingredients (recipe_id, ingredient, sort_order)
        VALUES (?, ?, ?)
      ");

      foreach ($ingredients as $index => $ingredient) {
        $ingredientSt->execute([$recipeId, $ingredient, $index + 1]);
      }

      $pdo->commit();
      return $recipeId;
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      throw $e;
    }
  }

  public static function approvedByArea(string $areaType): array
  {
    self::ensureSchema();
    $st = Database::conn()->prepare("
      SELECT r.*, u.first_name, u.last_name
      FROM recipes r
      JOIN users u ON u.id = r.user_id
      WHERE r.area_type=?
        AND r.status='approved'
      ORDER BY r.title ASC
    ");
    $st->execute([$areaType]);
    return self::withIngredients($st->fetchAll());
  }

  public static function allForAdmin(?string $areaType = null, ?string $status = null): array
  {
    self::ensureSchema();
    $where = [];
    $params = [];

    if (in_array($areaType, ['cocina', 'barra'], true)) {
      $where[] = 'r.area_type=?';
      $params[] = $areaType;
    }

    if (in_array($status, ['pending', 'approved'], true)) {
      $where[] = 'r.status=?';
      $params[] = $status;
    }

    $sql = "
      SELECT r.*, u.first_name, u.last_name
      FROM recipes r
      JOIN users u ON u.id = r.user_id
    ";

    if (!empty($where)) {
      $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= " ORDER BY FIELD(r.status, 'pending', 'approved'), r.area_type ASC, r.created_at DESC";

    $st = Database::conn()->prepare($sql);
    $st->execute($params);
    return self::withIngredients($st->fetchAll());
  }

  public static function find(int $id): ?array
  {
    self::ensureSchema();
    $st = Database::conn()->prepare("
      SELECT r.*, u.first_name, u.last_name
      FROM recipes r
      JOIN users u ON u.id = r.user_id
      WHERE r.id=?
      LIMIT 1
    ");
    $st->execute([$id]);
    $recipe = $st->fetch();
    if (!$recipe) {
      return null;
    }

    $recipe['ingredients'] = self::ingredientsFor($id);
    return $recipe;
  }

  public static function updateByAdmin(int $id, string $areaType, string $title, array $ingredients, string $preparation, string $status): void
  {
    self::ensureSchema();
    $title = trim($title);
    $preparation = trim($preparation);
    $ingredients = self::sanitizeIngredients($ingredients);
    $status = $status === 'approved' ? 'approved' : 'pending';
    $approvedAtSql = $status === 'approved' ? 'COALESCE(approved_at, NOW())' : 'NULL';

    if (!in_array($areaType, ['cocina', 'barra'], true)) {
      throw new RuntimeException('Area de recetario no valida.');
    }
    if ($title === '') {
      throw new RuntimeException('El titulo del producto es obligatorio.');
    }
    if (empty($ingredients)) {
      throw new RuntimeException('Debes agregar al menos un ingrediente.');
    }
    if ($preparation === '') {
      throw new RuntimeException('La preparacion es obligatoria.');
    }

    $pdo = Database::conn();
    $pdo->beginTransaction();

    try {
      $st = $pdo->prepare("
        UPDATE recipes
        SET area_type=?, title=?, preparation=?, status=?, approved_at={$approvedAtSql}
        WHERE id=?
      ");
      $st->execute([$areaType, $title, $preparation, $status, $id]);

      $delete = $pdo->prepare("DELETE FROM recipe_ingredients WHERE recipe_id=?");
      $delete->execute([$id]);

      $ingredientSt = $pdo->prepare("
        INSERT INTO recipe_ingredients (recipe_id, ingredient, sort_order)
        VALUES (?, ?, ?)
      ");
      foreach ($ingredients as $index => $ingredient) {
        $ingredientSt->execute([$id, $ingredient, $index + 1]);
      }

      $pdo->commit();
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      throw $e;
    }
  }

  public static function deleteByAdmin(int $id): void
  {
    self::ensureSchema();
    $pdo = Database::conn();
    $pdo->beginTransaction();

    try {
      $deleteIngredients = $pdo->prepare("DELETE FROM recipe_ingredients WHERE recipe_id=?");
      $deleteIngredients->execute([$id]);

      $deleteRecipe = $pdo->prepare("DELETE FROM recipes WHERE id=?");
      $deleteRecipe->execute([$id]);

      $pdo->commit();
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      throw $e;
    }
  }

  private static function withIngredients(array $recipes): array
  {
    foreach ($recipes as &$recipe) {
      $recipe['ingredients'] = self::ingredientsFor((int)$recipe['id']);
    }
    unset($recipe);

    return $recipes;
  }

  private static function ingredientsFor(int $recipeId): array
  {
    $st = Database::conn()->prepare("
      SELECT ingredient
      FROM recipe_ingredients
      WHERE recipe_id=?
      ORDER BY sort_order ASC, id ASC
    ");
    $st->execute([$recipeId]);
    return array_map(static fn($row) => $row['ingredient'], $st->fetchAll());
  }
}
