<?php

namespace App\Controllers;

use App\Services\MonitorService;

class Monitor extends BaseController
{
    public function index()
    {
        return view('monitor/index', ['history' => db_connect()->table('resource_metrics')->where('created_at >=', gmdate('Y-m-d H:i:s', time() - 86400))->orderBy('id', 'DESC')->limit(60)->get()->getResultArray()]);
    }

    public function metrics()
    {
        return $this->response->setJSON((new MonitorService(db_connect()))->collect());
    }
}
