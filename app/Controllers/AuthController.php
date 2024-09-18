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




public function create()
{
    $validation = \Config\Services::validation();
    
    $validation->setRules([
            'gender' => [
            'label'  => 'Gender',
            'rules'  => 'required|in_list[male,female,other]',
            'errors' => [
                'in_list' => 'The {field} must be one of: male, female, or other.',
            ]
        ]
    ]);

    if (!$validation->withRequest($this->request)->run()) {
        return redirect()->back()->withInput()->with('errors', $validation->getErrors());
    }

    // Proses input valid
}
