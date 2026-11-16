<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$rows = [['Nomor', 'Status'], ['TKT-001', 'Baru'], ['TKT-002', 'Selesai']];
$csvPath = __DIR__ . '/contoh.csv';
$xlsxPath = __DIR__ . '/contoh.xlsx';
$start = hrtime(true);
$file = fopen($csvPath, 'wb');
foreach ($rows as $row) {
    fputcsv($file, $row);
}
fclose($file);
$csvMs = (hrtime(true) - $start) / 1e6;
$start = hrtime(true);
$sheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet->getActiveSheet()->fromArray($rows);
$sheet->getActiveSheet()->getStyle('A1:B1')->getFont()->setBold(true);
(new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($sheet))->save($xlsxPath);
$xlsxMs = (hrtime(true) - $start) / 1e6;
echo 'CSV: ' . round($csvMs, 2) . ' ms, ' . filesize($csvPath) . " bytes\n";
echo 'XLSX: ' . round($xlsxMs, 2) . ' ms, ' . filesize($xlsxPath) . " bytes\n";
