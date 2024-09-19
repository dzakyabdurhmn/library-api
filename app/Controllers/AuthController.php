<?php

namespace App\Controllers;

use CodeIgniter\Database\Exceptions\DatabaseException;


class AuthController extends CoreController
{
    protected $format = 'json';


    public function get_all_users()
    {
        $db = \Config\Database::connect();

        // Ambil parameter dari query string
        $limit = $this->request->getVar('limit') ?? 10; // Default limit 10
        $page = $this->request->getVar('page') ?? 1; // Default page 1
        $search = $this->request->getVar('search');
        $filter = $this->request->getVar('filter');

        // Hitung offset untuk pagination
        $offset = ($page - 1) * $limit;

        try {
            // Query dasar tanpa mengikutkan password
            $query = "SELECT admin_id, admin_username, admin_email, admin_full_name, admin_nik, admin_role, admin_phone, admin_gender, admin_address FROM admin";
            $conditions = [];
            $params = [];

            // Tambahkan filter dan pencarian jika ada
            if ($search) {
                $conditions[] = "(admin_username LIKE ? OR admin_full_name LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }

            if ($filter) {
                $conditions[] = "admin_role = ?";
                $params[] = $filter;
            }

            if (count($conditions) > 0) {
                $query .= ' WHERE ' . implode(' AND ', $conditions);
            }

            // Tambahkan limit dan offset untuk pagination
            $query .= " LIMIT ? OFFSET ?";
            $params[] = (int) $limit;
            $params[] = (int) $offset;

            $users = $db->query($query, $params)->getResultArray();

            // Hitung total pengguna untuk pagination
            $totalQuery = "SELECT COUNT(*) as total FROM admin";
            if (count($conditions) > 0) {
                $totalQuery .= ' WHERE ' . implode(' AND ', $conditions);
            }
            $total = $db->query($totalQuery, $params)->getRow()->total;

            return $this->respondWithSuccess('Users retrieved successfully.', [
                'users' => $users,
                'total' => $total,
                'limit' => (int) $limit,
                'page' => (int) $page,
            ]);
        } catch (DatabaseException $e) {
            return $this->respondWithError('Failed to retrieve users: ' . $e->getMessage());
        }
    }





    public function get_user_by_id($admin_id)
    {
        $db = \Config\Database::connect();

        try {
            $query = "SELECT admin_id, admin_username, admin_email, admin_full_name, admin_nik, admin_role, admin_phone, admin_gender, admin_address  FROM admin WHERE admin_id = ?";
            $user = $db->query($query, [$admin_id])->getRowArray();

            if (!$user) {
                return $this->respondWithNotFound('User not found.');
            }

            return $this->respondWithSuccess('User found.', $user);
        } catch (DatabaseException $e) {
            return $this->respondWithError('Failed to retrieve user: ' . $e->getMessage());
        }
    }


    // Fungsi untuk mendaftarkan pengguna (hanya warehouse dan frontliner)
    public function register()
    {
        $db = \Config\Database::connect();

        // Validasi input
        $rules = [
            'username' => 'required|min_length[5]',
            'password' => 'required|min_length[8]',
            'email' => 'required|valid_email',
            'full_name' => 'required',
            'nik' => 'required|numeric|min_length[16]|max_length[16]',
            'role' => 'required|in_list[warehouse,frontliner]', // Validasi role, fix typo
            'phone' => 'required|numeric',
            'gender' => 'required|in_list[male,female]',
            'address' => 'required'
        ];

        // Validasi input berdasarkan rules
        if (!$this->validate($rules)) {
            return $this->respondWithValidationError('Validation errors', $this->validator->getErrors());
        }

        // Ambil data dari request
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

        try {
            // Hash password sebelum menyimpan ke database
            $data['admin_password'] = password_hash($data['admin_password'], PASSWORD_DEFAULT);

            // Raw query untuk memasukkan data ke tabel admin
            $query = "INSERT INTO admin (admin_username, admin_password, admin_email, admin_full_name, admin_nik, admin_role, admin_phone, admin_gender, admin_address) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

            // Eksekusi query
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
        } catch (DatabaseException $e) {
            return $this->respondWithError('Registration failed: ' . $e->getMessage());
        }
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


    public function delete_account($admin_id)
    {
        $db = \Config\Database::connect();

        try {
            // Cek apakah user dengan admin_id tersebut adalah warehouse atau frontliner
            $query = "SELECT admin_role FROM admin WHERE admin_id = ?";
            $user = $db->query($query, [$admin_id])->getRowArray();

            if (!$user) {
                return $this->respondWithError('User not found.');
            }

            if ($user['admin_role'] !== 'warehouse' && $user['admin_role'] !== 'frontliner') {
                return $this->respondWithUnauthorized('Only warehouse and frontliner users can be deleted.');
            }

            // Lakukan penghapusan data
            $deleteQuery = "DELETE FROM admin WHERE admin_id = ?";
            $db->query($deleteQuery, [$admin_id]);

            return $this->respondWithSuccess('Account deleted successfully.');
        } catch (DatabaseException $e) {
            return $this->respondWithError('Deletion failed: ' . $e->getMessage());
        }
    }

    // Fungsi untuk edit account (hanya warehouse dan frontliner)
    public function edit_account($admin_id)
    {
        $db = \Config\Database::connect();

        // Aturan validasi data yang akan diubah
        $rules = [
            'username' => 'required|min_length[5]',
            'email' => 'required|valid_email',
            'full_name' => 'required',
            'phone' => 'required|numeric',
            'address' => 'required'
        ];

        if (!$this->validate($rules)) {
            return $this->respondWithValidationError('Validation errors', $this->validator->getErrors());
        }

        $data = [
            'admin_username' => $this->request->getVar('username'),
            'admin_email' => $this->request->getVar('email'),
            'admin_full_name' => $this->request->getVar('full_name'),
            'admin_phone' => $this->request->getVar('phone'),
            'admin_address' => $this->request->getVar('address'),
        ];

        try {
            // Cek apakah user dengan admin_id tersebut adalah warehouse atau frontliner
            $query = "SELECT admin_role FROM admin WHERE admin_id = ?";
            $user = $db->query($query, [$admin_id])->getRowArray();

            if (!$user) {
                return $this->respondWithError('User not found.');
            }

            if ($user['admin_role'] !== 'warehouse' && $user['admin_role'] !== 'frontliner') {
                return $this->respondWithUnauthorized('Only warehouse and frontliner users can be edited.');
            }

            // Lakukan update data
            $updateQuery = "UPDATE admin SET admin_username = ?, admin_email = ?, admin_full_name = ?, admin_phone = ?, admin_address = ? WHERE admin_id = ?";
            $db->query($updateQuery, [
                $data['admin_username'],
                $data['admin_email'],
                $data['admin_full_name'],
                $data['admin_phone'],
                $data['admin_address'],
                $admin_id
            ]);

            return $this->respondWithSuccess('Account updated successfully.');
        } catch (DatabaseException $e) {
            return $this->respondWithError('Update failed: ' . $e->getMessage());
        }
    }
}

// https://chatgpt.com/c/66eb9d41-ab28-800e-b5e7-b911f2c723c0