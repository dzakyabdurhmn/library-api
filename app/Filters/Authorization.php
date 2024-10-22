<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\Config\Services;

class Authorization implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Load helper untuk validasi token
        helper('token_helper');

        // Ambil token dari header Authorization
        $token = $request->getHeaderLine('Authorization');
        if (!$token) {
            return Services::response()
                ->setJSON(['status' => 401, 'message' => 'Bearer token is required'])
                ->setStatusCode(401);
        }

        // Hapus kata "Bearer " jika ada
        $token = str_replace('Bearer ', '', $token);

        // Validasi token
        $validationResult = validateToken($token);
        if ($validationResult !== true) {
            return Services::response()
                ->setJSON($validationResult)
                ->setStatusCode(401);
        }

        // Token valid, lanjutkan ke request berikutnya
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Tidak perlu melakukan apa pun setelah request
    }
}
