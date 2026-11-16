<?php

namespace App\Controllers;

class Dashboard extends BaseController
{
    public function index()
    {
        return view('dashboard/index', ['data' => $this->data(), 'alerts' => session('role') === 'pemohon' ? [] : db_connect()->table('alerts')->where('resolved_at', null)->orderBy('id', 'DESC')->limit(5)->get()->getResultArray()]);
    }

    public function summary()
    {
        return $this->response->setJSON($this->data());
    }

    public function slideshow()
    {
        return view('dashboard/slideshow', ['data' => $this->data()]);
    }

    private function data(): array
    {
        $db = db_connect();
        $scope = $db->table('tickets')->where('deleted_at', null);
        if (session('role') === 'pemohon') {
            $scope->where('user_id', session('user_id'));
        }
        $total = (clone $scope)->countAllResults();
        $open = (clone $scope)->whereIn('status', ['Baru', 'Diproses'])->countAllResults();
        $late = (clone $scope)->whereIn('status', ['Baru', 'Diproses'])->where('sla_due_at <', gmdate('Y-m-d H:i:s'))->countAllResults();
        $resolved = (clone $scope)->select('AVG(TIMESTAMPDIFF(MINUTE, created_at, resolved_at)) AS minutes')->where('resolved_at !=', null)->get()->getRowArray();
        if (session('role') === 'pemohon') {
            $rows = $scope->select('status, category_id, DATE(created_at) AS ticket_date')->get()->getResultArray();
            $status = [];
            $category = [];
            $days = [];
            foreach ($rows as $row) {
                $status[$row['status']] = ($status[$row['status']] ?? 0) + 1;
                $category[$row['category_id']] = ($category[$row['category_id']] ?? 0) + 1;
                $days[$row['ticket_date']] = ($days[$row['ticket_date']] ?? 0) + 1;
            }
            $byStatus = array_map(static fn ($key, $value) => ['status' => $key, 'total' => $value], array_keys($status), $status);
            $categoryNames = array_column($db->table('categories')->select('id,name')->get()->getResultArray(), 'name', 'id');
            $byCategory = array_map(static fn ($key, $value) => ['category_id' => $key, 'category_name' => $categoryNames[$key] ?? 'Lainnya', 'total' => $value], array_keys($category), $category);
            $byDay = array_map(static fn ($key, $value) => ['ticket_date' => $key, 'total' => $value], array_keys($days), $days);
        } else {
            $byStatus = $db->table('v_dashboard')->get()->getResultArray();
            $byCategory = $db->table('v_ticket_by_category')->get()->getResultArray();
            $byDay = $db->table('v_ticket_by_day')->where('ticket_date >=', gmdate('Y-m-d', time() - 29 * 86400))->orderBy('ticket_date')->get()->getResultArray();
        }
        $topOfficer = $db->table('users u')->select('u.name, COUNT(t.id) AS total')->join('tickets t', "t.assigned_to=u.id AND t.status IN ('Selesai','Ditutup') AND t.deleted_at IS NULL", 'left')->where('u.role', 'petugas')->groupBy('u.id, u.name')->orderBy('total', 'DESC')->limit(1)->get()->getRowArray();
        return ['cards' => ['total' => $total, 'open' => $open, 'overdue' => $late, 'average_minutes' => round((float) ($resolved['minutes'] ?? 0), 1)], 'by_status' => $byStatus, 'by_category' => $byCategory, 'by_day' => $byDay, 'top_officer' => $topOfficer];
    }
}
