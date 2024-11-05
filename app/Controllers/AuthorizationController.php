<?php

namespace App\Controllers;

use Config\Database;


class AuthorizationController extends CoreController
{
    protected $format = 'json';







    function validateToken($role)
    {
        $db = Database::connect();

        $token = $this->request->getHeaderLine('token');


        // dah bisa tinggal gimana entar
        $roleArray = explode(',', $role);


        if (!$token) {
            return ['status' => 400, 'message' => 'Token is required.'];
        }

        // Cek token di database
        $query = "SELECT * FROM admin_token WHERE admin_token_token = ?";
        $tokenData = $db->query($query, [$token])->getRowArray();


        if (!$tokenData) {
            return ['status' => 400, 'message' => 'Invalid token.'];
        }

        $sql_get_employee = "SELECT admin_role FROM admin WHERE admin_id = ?";
        $get_employee = $db->query($sql_get_employee, [$tokenData['admin_token_admin_id']])->getRowArray();
        $admin_role = $get_employee['admin_role'];





        if (!in_array($admin_role, $roleArray)) {
            return ['status' => 400, 'message' => "only $role can acess this data"];
        }
        if (!$tokenData) {
            return ['status' => 400, 'message' => 'Invalid token.'];
        }




        // Cek apakah token sudah expired
        $currentTimestamp = time();
        $expiresAt = strtotime($tokenData['admin_token_expires_at']);

        if ($expiresAt < $currentTimestamp) {
            return ['status' => 400, 'message' => 'Token has expired.'];
        }




        // Jika token valid
        return true;
    }


}

