<?php

function validateToken($token)
{
    // Ambil token dari file .env
    $appToken = getenv('token');

    if (!$token) {
        return ['status' => 400, 'message' => 'Token is required.'];
    }

    // Cek apakah token cocok dengan yang ada di .env
    if ($token !== $appToken) {
        return ['status' => 400, 'message' => 'Invalid token.'];
    }

    // Jika token valid
    return true;
}
