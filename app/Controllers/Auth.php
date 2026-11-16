<?php

namespace App\Controllers;

use App\Services\ActivityLogger;
use App\Services\OtpService;

class Auth extends BaseController
{
    public function login()
    {
        if (session('user_id')) {
            return redirect()->to('/');
        }
        return view('auth/login');
    }

    public function attempt()
    {
        $email = trim((string) $this->request->getPost('email'));
        $password = (string) $this->request->getPost('password');
        $db = db_connect();
        $user = $db->table('users')->where('email', $email)->get()->getRowArray();
        if (! $user || ! (int) $user['is_active'] || ! password_verify($password, $user['password_hash']) || ($user['locked_until'] && $user['locked_until'] > gmdate('Y-m-d H:i:s'))) {
            return redirect()->back()->with('error', 'Email, password, atau status akun tidak valid.');
        }
        session()->regenerate();
        session()->set('pending_user', (int) $user['id']);
        $code = (new OtpService($db))->issue((int) $user['id']);
        $localHost = parse_url((string) config('App')->baseURL, PHP_URL_HOST);
        if (in_array($localHost, ['localhost', '127.0.0.1'], true) && filter_var(env('DEMO_OTP', false), FILTER_VALIDATE_BOOLEAN)) {
            session()->set('demo_otp', $code);
        }
        (new ActivityLogger($db))->write((int) $user['id'], 'otp_issued', 'users', (int) $user['id']);
        return redirect()->to('/otp');
    }

    public function otp()
    {
        if (! session('pending_user')) {
            return redirect()->to('/login');
        }
        return view('auth/otp', ['demoOtp' => session('demo_otp')]);
    }

    public function verify()
    {
        $id = (int) session('pending_user');
        if (! $id) {
            return redirect()->to('/login');
        }
        $db = db_connect();
        if (! (new OtpService($db))->verify($id, (string) $this->request->getPost('code'))) {
            (new ActivityLogger($db))->write($id, 'otp_failed', 'users', $id);
            return redirect()->back()->with('error', 'OTP salah, kedaluwarsa, atau akun terkunci.');
        }
        $user = $db->table('users')->where('id', $id)->get()->getRowArray();
        session()->regenerate();
        session()->remove(['pending_user', 'demo_otp']);
        session()->set(['user_id' => $id, 'role' => $user['role'], 'name' => $user['name']]);
        (new ActivityLogger($db))->write($id, 'login', 'users', $id);
        return redirect()->to('/');
    }

    public function logout()
    {
        (new ActivityLogger(db_connect()))->write((int) session('user_id'), 'logout', 'users', (int) session('user_id'));
        session()->destroy();
        return redirect()->to('/login');
    }
}
