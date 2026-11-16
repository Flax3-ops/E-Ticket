<?php

namespace App\Controllers;

class Alerts extends BaseController
{
    public function index()
    {
        return view('alerts/index', ['items' => db_connect()->table('alerts')->orderBy('id', 'DESC')->limit(100)->get()->getResultArray()]);
    }

    public function acknowledge(int $id)
    {
        $db = db_connect();
        $db->table('alerts')->where('id', $id)->where('resolved_at', null)->update(['acknowledged_by' => (int) session('user_id'), 'acknowledged_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s')]);
        (new \App\Services\ActivityLogger($db))->write((int) session('user_id'), 'alert_acknowledged', 'alerts', $id);
        return redirect()->to('/alerts')->with('success', 'Alert diakui. Alert baru pulih setelah dua pemeriksaan normal.');
    }

    public function active()
    {
        return $this->response->setJSON(db_connect()->table('alerts')->where('resolved_at', null)->orderBy('id', 'DESC')->get()->getResultArray());
    }
}
