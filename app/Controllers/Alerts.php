<?php

namespace App\Controllers;

class Alerts extends BaseController
{
    public function index()
    {
        return view('alerts/index', ['items' => db_connect()->table('alerts')->orderBy('id', 'DESC')->limit(100)->get()->getResultArray()]);
    }

    public function active()
    {
        return $this->response->setJSON(db_connect()->table('alerts')->where('resolved_at', null)->orderBy('id', 'DESC')->get()->getResultArray());
    }
}
