<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class RoleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Pemeriksaan sesi dan status akun memakai logika yang sama dengan AuthFilter.
        $authResult = (new AuthFilter())->before($request);
        if ($authResult !== null) {
            return $authResult;
        }
        if (! in_array(session('role'), $arguments ?? [], true)) {
            return service('response')->setStatusCode(403)->setBody('Akses ditolak.');
        }
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
