<?php

namespace App\Controllers;

use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\RESTful\ResourceController;

class AuthController extends CoreController
{
    protected $format = 'json';

    // Fungsi untuk mendaftarkan pengguna (hanya warehouse dan frontliner)
    public function register()
    {
        $db = \Config\Database::connect();


        $rules = [
            'username' => 'required|min_length[5]',
            'password' => 'required|min_length[8]',
            'email' => 'required|valid_email',
            'full_name' => 'required',
            'nik' => 'required|numeric|min_length[16]|max_length[16]',
            'role' => 'required|in_list[wearhouse,frontliner]', // Validasi role
            'phone' => 'required|numeric',
            'gender' => 'required|in_list[male,female]',
            'address' => 'required'
        ];

        if (!$this->validate($rules)) {
            return $this->respondWithValidationError('Validation errors', $this->validator->getErrors());
        }

        $data = [
            'admin_username' => $this->request->getVar('username'),
            'admin_password' => $this->request->getVar('password'),
            'admin_email' => $this->request->getVar('email'),
            'admin_full_name' => $this->request->getVar('full_name'),
            'admin_nik' => $this->request->getVar('nik'),
            'admin_role' => $this->request->getVar('role'),
            'admin_phone' => $this->request->getVar('phone'),
            'admin_gender' => $this->request->getVar('gender'),
            'admin_address' => $this->request->getVar('address'),
        ];


        // Validasi role apakah frontliner atau warehouse
        if ($data['admin_role'] === 'superadmin') {
            return $this->respondWithValidationError('Superadmin cannot register.');
        }

        try {
            if ($data['admin_role'] == 'warehouse' || $data['admin_role'] == 'frontliner') {
                // Hash password sebelum memasukkan ke database
                $data['admin_password'] = password_hash($data['admin_password'], PASSWORD_DEFAULT);

                // Raw query untuk insert data
                $query = "INSERT INTO admin (admin_username, admin_password, admin_email, admin_full_name, admin_nik, admin_role, admin_phone, admin_gender, admin_address) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

                $db->query($query, [
                    $data['admin_username'],
                    $data['admin_password'],
                    $data['admin_email'],
                    $data['admin_full_name'],
                    $data['admin_nik'],
                    $data['admin_role'],
                    $data['admin_phone'],
                    $data['admin_gender'],
                    $data['admin_address']
                ]);

                return $this->respondWithSuccess('Registration successful.');
            }
        } catch (DatabaseException $e) {
            return $this->respondWithError('Registration failed : ' . $e->getMessage());
        }

        return $this->respondWithError(message: 'Registration failed.');
    }

    // Fungsi untuk login (termasuk superadmin)
    public function login()
    {
        $db = \Config\Database::connect();




        $rules = [
            'username' => 'required',
            'password' => 'required'
        ];

        if (!$this->validate($rules)) {
            return $this->respondWithValidationError('Validation errors', $this->validator->getErrors());
        }



        $username = $this->request->getVar('username');
        $password = $this->request->getVar('password');


        try {
            // Raw query untuk mendapatkan data pengguna berdasarkan username
            $query = "SELECT * FROM admin WHERE admin_username = ?";
            $user = $db->query($query, [$username])->getRowArray();

            if ($user && password_verify($password, $user['admin_password'])) {
                // Simpan sesi pengguna (di sini hanya contoh, sebaiknya gunakan JWT untuk API)
                $sessionData = [
                    'admin_id' => $user['admin_id'],
                    'admin_username' => $user['admin_username'],
                    'admin_role' => $user['admin_role'],
                ];

                return $this->respondWithSuccess('Login successful.', $sessionData);
            }

            return $this->respondWithUnauthorized('Invalid username or password.');
        } catch (DatabaseException $e) {
            return $this->respondWithError('Login failed: ' . $e->getMessage());
        }
    }

    // Fungsi logout (hanya contoh)
    public function logout()
    {
        return $this->respondWithSuccess('Logged out successfully.');
    }
}