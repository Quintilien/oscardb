<?php
declare(strict_types=1);

/*
 * OscarDB
 * Copyright (C) 2026 PhS & Quintilien
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

// Generic Excel export of OscarDB views (1.23), driven by the YAML metadata
// of each column.
//
// Only loaded (require) when list.php receives export=1, so that the Composer /
// PhpSpreadsheet autoloader is never loaded for normal (HTML) requests.
//
// Usage (from list.php):
//   require dirname(__DIR__).'/src/export_excel.php';
//   export_array_to_excel($filename, $columns, $rows);
//
// $columns = [
//     'field' => [
//         'label'   => 'Column label',               // header text
//         'kind'    => 'int'|'real'|'date'|'text',   // see column_kind() in lib.php
//         'boolean' => bool,                         // true if YAML type = "boolean"
//     ],
//     ...
// ]
// $rows = [ ['field' => value, ...], ... ]

function export_array_to_excel(string $filename, array $columns, array $rows): void
{
    // ---- Load PhpSpreadsheet (vendor/ at the project root, outside public/)

    $autoload = OSCAR_ROOT . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        http_response_code(500);
        echo "PhpSpreadsheet introuvable : le dossier vendor/ doit se trouver à côté de public/.";
        exit;
    }
    require_once $autoload;

    // ---- Imports
    $Coordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::class;
    $DataType   = \PhpOffice\PhpSpreadsheet\Cell\DataType::class;

    // ---- Workbook
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Export');

    // ---- Headers (row 1)
    $colIndex = 1;
    foreach ($columns as $field => $meta) {
        $cell = $Coordinate::stringFromColumnIndex($colIndex) . '1';
        // 5.0.1: text, never a formula (a label starting with "=" stayed a formula)
        $sheet->setCellValueExplicit($cell, (string)($meta['label'] ?? $field), $DataType::TYPE_STRING);
        $colIndex++;
    }

    // Header style
    $lastColLetter = $Coordinate::stringFromColumnIndex(count($columns));
    $sheet->getStyle("A1:{$lastColLetter}1")->getFont()->setBold(true);
    $sheet->freezePane('A2');

    // ---- Data (from row 2)
    $rowNum = 2;

    foreach ($rows as $row) {
        $colIndex = 1;

        foreach ($columns as $field => $meta) {
            $val  = $row[$field] ?? null;
            $cell = $Coordinate::stringFromColumnIndex($colIndex) . (string)$rowNum;

            $kind      = $meta['kind'] ?? 'text';
            $isBoolean = $meta['boolean'] ?? false;

            if ($val === null || $val === '') {
                // Empty cell
                $sheet->setCellValue($cell, '');
            } elseif ($isBoolean) {

                // Excel's NATIVE boolean type (TYPE_BOOL) rather than a fixed
                // "Oui"/"Non" text: Excel displays it in the user's language
                // (VRAI/FAUX in French, TRUE/FALSE in English, etc.).
                $sheet->setCellValueExplicit($cell, (bool)intval($val), $DataType::TYPE_BOOL);
            } elseif ($kind === 'int' && is_numeric($val)) {
                $sheet->setCellValue($cell, (int)$val);
            } elseif ($kind === 'real' && is_numeric($val)) {
                // Native Excel number (no formatted text with separators:
                // Excel handles the display itself).
                $sheet->setCellValue($cell, (float)$val);
            } else {
                // Text (dates included: kept as text, a deliberately simple
                // choice that avoids Excel's locale-dependent date conversions).
                $sheet->setCellValueExplicit($cell, (string)$val, $DataType::TYPE_STRING);
            }

            $colIndex++;
        }

        $rowNum++;
    }

    // Auto-size columns (fine for reasonably sized exports)
    for ($i = 1; $i <= count($columns); $i++) {
        $sheet->getColumnDimension($Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
    }

    // ---- XLSX HTTP headers
    if (!str_ends_with(strtolower($filename), '.xlsx')) {
        $filename .= '.xlsx';
    }

    // No output before the headers
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    // 5.0.1: sanitized file name (quotes, slashes… coming from a view or database name)
    $filename = preg_replace('/[^\p{L}\p{N}._ -]/u', '_', $filename) ?? 'export.xlsx';
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
