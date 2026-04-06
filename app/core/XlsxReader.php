<?php

class XlsxReader
{
  public static function rows(string $path): array
  {
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
      throw new RuntimeException('No se pudo abrir el archivo Excel.');
    }

    try {
      $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
      if ($sheetXml === false) {
        throw new RuntimeException('El archivo Excel no contiene la hoja principal esperada.');
      }

      $sharedStrings = self::sharedStrings($zip->getFromName('xl/sharedStrings.xml'));
      $sheet = simplexml_load_string($sheetXml);
      if (!$sheet) {
        throw new RuntimeException('No se pudo leer la hoja del archivo Excel.');
      }

      $sheet->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
      $rowNodes = $sheet->xpath('//a:sheetData/a:row') ?: [];

      $rows = [];
      foreach ($rowNodes as $rowNode) {
        $row = [];
        foreach ($rowNode->c as $cell) {
          $attrs = $cell->attributes();
          $ref = (string)($attrs['r'] ?? '');
          $type = (string)($attrs['t'] ?? '');
          $row[self::columnIndex($ref)] = self::cellValue($cell, $type, $sharedStrings);
        }

        if (!$row) {
          continue;
        }

        ksort($row);
        $rows[] = array_values($row);
      }

      return $rows;
    } finally {
      $zip->close();
    }
  }

  private static function sharedStrings($xml): array
  {
    if ($xml === false) {
      return [];
    }

    $doc = simplexml_load_string($xml);
    if (!$doc) {
      return [];
    }

    $doc->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $items = $doc->xpath('//a:si') ?: [];

    $strings = [];
    foreach ($items as $item) {
      $parts = $item->xpath('.//a:t') ?: [];
      $text = '';
      foreach ($parts as $part) {
        $text .= (string)$part;
      }
      $strings[] = $text;
    }

    return $strings;
  }

  private static function cellValue(SimpleXMLElement $cell, string $type, array $sharedStrings): string
  {
    if ($type === 'inlineStr') {
      $cell->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
      $parts = $cell->xpath('.//a:t') ?: [];
      $text = '';
      foreach ($parts as $part) {
        $text .= (string)$part;
      }
      return trim($text);
    }

    $raw = trim((string)($cell->v ?? ''));
    if ($type === 's') {
      return trim((string)($sharedStrings[(int)$raw] ?? ''));
    }

    return $raw;
  }

  private static function columnIndex(string $ref): int
  {
    $letters = preg_replace('/[^A-Z]/', '', strtoupper($ref));
    $index = 0;

    for ($i = 0; $i < strlen($letters); $i++) {
      $index = ($index * 26) + (ord($letters[$i]) - 64);
    }

    return max(0, $index - 1);
  }
}
