<?php

namespace App\Controllers;

use Config\Database;


class AuthorizationController extends CoreController
{
    protected $format = 'json';


    function validateToken($token)
    {
        $db = Database::connect();

        if (!$token) {
            return ['status' => 401, 'message' => 'Token is required.'];
        }

        // Cek token di database
        $query = "SELECT * FROM admin_token WHERE token = ?";
        $tokenData = $db->query($query, [$token])->getRowArray();

        if (!$tokenData) {
            return ['status' => 401, 'message' => 'Invalid token.'];
        }

        // Cek apakah token sudah expired
        $currentTimestamp = time();
        $expiresAt = strtotime($tokenData['expires_at']);

        if ($expiresAt < $currentTimestamp) {
            return ['status' => 401, 'message' => 'Token has expired.'];
        }

        // Jika token valid
        return true;
    }


}

