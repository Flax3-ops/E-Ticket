<?php

use CodeIgniter\HTTP\ResponseInterface;

if (! function_exists('friendly_error')) {
    /**
     * Respons galat ramah pengguna yang menampilkan kode kesalahan internal (F-LOG-04).
     * Pesan dan kode di-escape; kode mengikuti pola E-<MODUL>-<HTTP>, mis. E-ACL-403.
     */
    function friendly_error(int $status, string $code, string $message): ResponseInterface
    {
        log_message('notice', '{code} {message} (HTTP {status})', ['code' => $code, 'message' => $message, 'status' => $status]);
        return service('response')->setStatusCode($status)->setBody(view('errors/friendly', ['status' => $status, 'code' => $code, 'message' => $message]));
    }
}
