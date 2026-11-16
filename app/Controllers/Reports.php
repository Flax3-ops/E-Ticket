<?php

namespace App\Controllers;

use Dompdf\Dompdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class Reports extends BaseController
{
    public function tickets()
    {
        $db = db_connect();
        $builder = $db->table('tickets t')->select('t.ticket_no, t.title, t.priority, t.status, t.created_at, c.name AS category_name')->join('categories c', 'c.id=t.category_id')->where('t.deleted_at', null);
        foreach (['status', 'priority', 'category_id'] as $field) {
            if ($value = $this->request->getGet($field)) {
                $builder->where('t.' . $field, $value);
            }
        }
        if ($search = trim((string) $this->request->getGet('q'))) {
            if (str_starts_with(strtoupper($search), 'TKT-')) {
                $builder->like('t.ticket_no', $search, 'after');
            } elseif (mb_strlen($search) >= 3) {
                $builder->where('MATCH(t.title) AGAINST (' . $db->escape($search) . ' IN NATURAL LANGUAGE MODE)', null, false);
            } else {
                $builder->like('t.title', $search);
            }
        }
        $sheet = (new Spreadsheet())->getActiveSheet();
        $sheet->fromArray(['Nomor', 'Judul', 'Kategori', 'Prioritas', 'Status', 'Dibuat UTC'], null, 'A1');
        $row = 2;
        foreach ($builder->orderBy('t.id', 'DESC')->get()->getResultArray() as $ticket) {
            $sheet->fromArray([$ticket['ticket_no'], $ticket['title'], $ticket['category_name'], $ticket['priority'], $ticket['status'], $ticket['created_at']], null, 'A' . $row++);
        }
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);
        foreach (range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $file = tempnam(sys_get_temp_dir(), 'tiket-xlsx-');
        (new Xlsx($sheet->getParent()))->save($file);
        $body = file_get_contents($file);
        unlink($file);
        return $this->response->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')->setHeader('Content-Disposition', 'attachment; filename="tiket-layanan.xlsx"')->setBody($body);
    }

    public function monthly()
    {
        $month = (string) ($this->request->getGet('month') ?: gmdate('Y-m'));
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            return $this->response->setStatusCode(400)->setBody('Bulan tidak valid.');
        }
        $rows = db_connect()->table('tickets')->select('status, COUNT(*) AS total')->where('deleted_at', null)->like('created_at', $month, 'after')->groupBy('status')->get()->getResultArray();
        $html = '<h1>Laporan Tiket ' . esc($month) . '</h1><table border="1" cellpadding="8"><tr><th>Status</th><th>Jumlah</th></tr>';
        foreach ($rows as $row) {
            $html .= '<tr><td>' . esc($row['status']) . '</td><td>' . esc($row['total']) . '</td></tr>';
        }
        $pdf = new Dompdf();
        $pdf->loadHtml($html . '</table>');
        $pdf->render();
        return $this->response->setHeader('Content-Type', 'application/pdf')->setHeader('Content-Disposition', 'attachment; filename="laporan-' . $month . '.pdf"')->setBody($pdf->output());
    }
}
