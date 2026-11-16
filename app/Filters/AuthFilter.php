<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $isApi = str_starts_with(ltrim($request->getUri()->getPath(), '/'), 'api/') || str_ends_with($request->getUri()->getPath(), 'monitor/metrics');
        if (! session('user_id')) {
            if ($isApi) {
                // SRS 7.2: endpoint JSON tanpa izin mengembalikan HTTP 403, bukan redirect HTML.
                return service('response')->setStatusCode(403)->setJSON(['error' => 'forbidden']);
            }
            return redirect()->to('/login')->with('error', 'Silakan masuk terlebih dahulu.');
        }
        // Sesi dicek ulang ke DB: akun yang dinonaktifkan atau berubah peran langsung berlaku.
        $user = db_connect()->table('users')->select('role, name, is_active')->where('id', (int) session('user_id'))->get()->getRowArray();
        if (! $user || ! (int) $user['is_active']) {
            session()->destroy();
            return redirect()->to('/login')->with('error', 'Akun tidak aktif. Hubungi admin.');
        }
        session()->set(['role' => $user['role'], 'name' => $user['name']]);
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
