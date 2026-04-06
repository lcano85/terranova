<?php

class Pagination
{
  public static function paginateArray(array $items, string $pageParam = 'page', string $perPageParam = 'per_page', array $allowedPerPage = [10, 20, 50, 100]): array
  {
    $allowedPerPage = array_values(array_unique(array_map('intval', $allowedPerPage)));
    sort($allowedPerPage);

    $defaultPerPage = $allowedPerPage[0] ?? 10;
    $perPage = (int)($_GET[$perPageParam] ?? $defaultPerPage);
    if (!in_array($perPage, $allowedPerPage, true)) {
      $perPage = $defaultPerPage;
    }

    $total = count($items);
    $totalPages = max(1, (int)ceil($total / $perPage));
    $page = (int)($_GET[$pageParam] ?? 1);
    if ($page < 1) {
      $page = 1;
    }
    if ($page > $totalPages) {
      $page = $totalPages;
    }

    $offset = ($page - 1) * $perPage;
    $rows = array_slice($items, $offset, $perPage);

    return [
      'rows' => $rows,
      'meta' => [
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'total_pages' => $totalPages,
        'page_param' => $pageParam,
        'per_page_param' => $perPageParam,
        'allowed_per_page' => $allowedPerPage,
      ],
    ];
  }

  public static function render(array $meta): string
  {
    if (($meta['total'] ?? 0) <= 0) {
      return '';
    }

    $page = (int)$meta['page'];
    $perPage = (int)$meta['per_page'];
    $total = (int)$meta['total'];
    $totalPages = (int)$meta['total_pages'];
    $pageParam = (string)$meta['page_param'];
    $perPageParam = (string)$meta['per_page_param'];
    $allowed = $meta['allowed_per_page'] ?? [10, 20, 50, 100];

    $from = (($page - 1) * $perPage) + 1;
    $to = min($total, $page * $perPage);

    $html = '<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mt-3">';
    $html .= '<div class="text-muted small">Mostrando ' . $from . ' a ' . $to . ' de ' . $total . ' registros</div>';
    $html .= '<div class="d-flex flex-column flex-sm-row align-items-sm-center gap-2">';

    $html .= '<form method="GET" class="d-flex align-items-center gap-2 mb-0">';
    foreach ($_GET as $key => $value) {
      if ($key === $pageParam || $key === $perPageParam) {
        continue;
      }
      if (is_array($value)) {
        foreach ($value as $item) {
          $html .= '<input type="hidden" name="' . self::e($key) . '[]" value="' . self::e((string)$item) . '">';
        }
        continue;
      }
      $html .= '<input type="hidden" name="' . self::e($key) . '" value="' . self::e((string)$value) . '">';
    }
    $html .= '<input type="hidden" name="' . self::e($pageParam) . '" value="1">';
    $html .= '<label class="small text-muted mb-0" for="' . self::e($perPageParam) . '">Ver</label>';
    $html .= '<select class="form-select form-select-sm" style="width:auto;" id="' . self::e($perPageParam) . '" name="' . self::e($perPageParam) . '" onchange="this.form.submit()">';
    foreach ($allowed as $option) {
      $selected = (int)$option === $perPage ? ' selected' : '';
      $html .= '<option value="' . (int)$option . '"' . $selected . '>' . (int)$option . '</option>';
    }
    $html .= '</select>';
    $html .= '</form>';

    if ($totalPages > 1) {
      $html .= '<nav aria-label="Paginacion"><ul class="pagination pagination-sm mb-0">';
      $html .= self::pageItem('&laquo;', max(1, $page - 1), $page === 1, $pageParam, $perPageParam, $perPage);

      for ($i = 1; $i <= $totalPages; $i++) {
        if ($i === 1 || $i === $totalPages || abs($i - $page) <= 1) {
          $html .= self::pageItem((string)$i, $i, false, $pageParam, $perPageParam, $perPage, $i === $page);
          continue;
        }

        if (abs($i - $page) === 2) {
          $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
      }

      $html .= self::pageItem('&raquo;', min($totalPages, $page + 1), $page === $totalPages, $pageParam, $perPageParam, $perPage);
      $html .= '</ul></nav>';
    }

    $html .= '</div></div>';
    return $html;
  }

  private static function pageItem(string $label, int $targetPage, bool $disabled, string $pageParam, string $perPageParam, int $perPage, bool $active = false): string
  {
    $classes = ['page-item'];
    if ($disabled) {
      $classes[] = 'disabled';
    }
    if ($active) {
      $classes[] = 'active';
    }

    $html = '<li class="' . implode(' ', $classes) . '">';
    if ($disabled || $active) {
      $html .= '<span class="page-link">' . $label . '</span>';
    } else {
      $html .= '<a class="page-link" href="' . self::e(self::url($pageParam, $targetPage, $perPageParam, $perPage)) . '">' . $label . '</a>';
    }
    $html .= '</li>';
    return $html;
  }

  private static function url(string $pageParam, int $page, string $perPageParam, int $perPage): string
  {
    $query = $_GET;
    $query[$pageParam] = $page;
    $query[$perPageParam] = $perPage;

    $qs = http_build_query($query);
    $path = strtok($_SERVER['REQUEST_URI'] ?? '', '?') ?: '';
    return $path . ($qs !== '' ? '?' . $qs : '');
  }

  private static function e(string $value): string
  {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
  }
}
