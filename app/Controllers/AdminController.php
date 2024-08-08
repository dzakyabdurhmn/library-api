<?php

namespace App\Controllers;

class AdminController extends CoreController
{
    protected $modelName = 'App\Models\UserModel';
    protected $format = 'json';

    public function register()
    {
        $rules = [
            'username' => 'required|min_length[3]|max_length[100]|is_unique[admin.username]',
            'password' => 'required|min_length[8]',
            'email' => 'required|min_length[8]|is_unique[admin.email]',
            'full_name' => 'required|min_length[8]',
            'nik' => 'required|min_length[8]|is_unique[admin.nik]',
        ];

        if (!$this->validate($rules)) {
            return $this->respondWithValidationError('Validation errors', $this->validator->getErrors());
        }

        $db = \Config\Database::connect();
        $query = "INSERT INTO admin (username, password, email, full_name, nik) VALUES (:username:, :password:, :email:, :full_name:, :nik:)";
        $params = [
            'username' => $this->request->getVar('username'),
            'password' => password_hash($this->request->getVar('password'), PASSWORD_DEFAULT),
            'email' => $this->request->getVar('email'),
            'full_name' => $this->request->getVar('full_name'),
            'nik' => $this->request->getVar('nik'),
        ];

        $db->query($query, $params);

        return $this->respondWithSuccess('User registered successfully', null, 201);
    }

    public function login()
    {
        $rules = [
            'username' => 'required',
            'password' => 'required'
        ];

        if (!$this->validate($rules)) {
            return $this->respondWithValidationError('Validation errors', $this->validator->getErrors());
        }

        $db = \Config\Database::connect();
        $query = "SELECT * FROM admin WHERE username = :username:";
        $params = [
            'username' => $this->request->getVar('username')
        ];

        $user = $db->query($query, $params)->getRowArray();

        if (!$user || !password_verify($this->request->getVar('password'), $user['password'])) {
            return $this->respondWithUnauthorized('Invalid login credentials');
        }

        return $this->respondWithSuccess('Login successful', null, 200);
    }
}



/**
 * pagination
 * search
 * filter
 * report nya
 */
