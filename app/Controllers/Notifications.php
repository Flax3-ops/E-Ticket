<?php

namespace App\Controllers;

class Notifications extends BaseController
{
    public function index()
    {
        return view('notifications/index', ['items' => db_connect()->table('notifications')->where('user_id', session('user_id'))->orderBy('id', 'DESC')->limit(100)->get()->getResultArray()]);
    }

    public function unread()
    {
        $db = db_connect();
        $items = $db->table('notifications')->where('user_id', session('user_id'))->where('read_at', null)->orderBy('id', 'DESC')->limit(10)->get()->getResultArray();
        return $this->response->setJSON(['count' => $db->table('notifications')->where('user_id', session('user_id'))->where('read_at', null)->countAllResults(), 'items' => $items]);
    }

    public function read(int $id)
    {
        db_connect()->table('notifications')->where('id', $id)->where('user_id', session('user_id'))->update(['read_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s')]);
        return redirect()->to('/notifications');
    }
}
