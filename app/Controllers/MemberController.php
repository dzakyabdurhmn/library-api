<?php

namespace App\Controllers;

use CodeIgniter\Database\Exceptions\DatabaseException;

class MemberController extends CoreController
{
    // Fungsi untuk menambahkan member baru (Create)
    public function create()
    {
        $db = \Config\Database::connect();

        $rules = [
            'member_username' => 'required|min_length[5]',
            'member_email' => 'required|valid_email',
            'member_full_name' => 'required',
            'member_address' => 'required',
            'member_job' => 'required',
            'member_status' => 'required',
            'member_religion' => 'required',
            'member_barcode' => 'required',
            'member_gender' => 'required|in_list[MEN,WOMEN]'
        ];

        if (!$this->validate($rules)) {
            return $this->respondWithValidationError('Validation errors', $this->validator->getErrors());
        }

        $data = [
            'member_username' => $this->request->getVar('member_username'),
            'member_email' => $this->request->getVar('member_email'),
            'member_full_name' => $this->request->getVar('member_full_name'),
            'member_address' => $this->request->getVar('member_address'),
            'member_job' => $this->request->getVar('member_job'),
            'member_status' => $this->request->getVar('member_status'),
            'member_religion' => $this->request->getVar('member_religion'),
            'member_barcode' => $this->request->getVar('member_barcode'),
            'member_gender' => $this->request->getVar('member_gender'),
        ];

        try {
            // Raw query untuk insert data member
            $query = "INSERT INTO member (member_username, member_email, member_full_name, member_address, member_job, member_status, member_religion, member_barcode, member_gender) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $db->query($query, [
                $data['member_username'],
                $data['member_email'],
                $data['member_full_name'],
                $data['member_address'],
                $data['member_job'],
                $data['member_status'],
                $data['member_religion'],
                $data['member_barcode'],
                $data['member_gender']
            ]);

            return $this->respondWithSuccess('Member added successfully.');
        } catch (DatabaseException $e) {
            return $this->respondWithError('Failed to add member: ' . $e->getMessage());
        }
    }

    // Fungsi untuk mendapatkan detail member berdasarkan member_id (Read)
    public function show($id = null)
    {
        $db = \Config\Database::connect();

        try {
            // Raw query untuk mendapatkan data member berdasarkan member_id
            $query = "SELECT * FROM member WHERE member_id = ?";
            $member = $db->query($query, [$id])->getRowArray();

            if (!$member) {
                return $this->respondWithNotFound('Member not found.');
            }

            return $this->respondWithSuccess('Member found.', $member);
        } catch (DatabaseException $e) {
            return $this->respondWithError('Failed to retrieve member: ' . $e->getMessage());
        }
    }

    // Fungsi untuk memperbarui data member (Update)
    public function update($id = null)
    {
        $db = \Config\Database::connect();

        $rules = [
            'member_username' => 'required|min_length[5]',
            'member_email' => 'required|valid_email',
            'member_full_name' => 'required',
            'member_address' => 'required',
            'member_job' => 'required',
            'member_status' => 'required',
            'member_religion' => 'required',
            'member_barcode' => 'required',
            'member_gender' => 'required|in_list[MEN,WOMEN]'
        ];

        if (!$this->validate($rules)) {
            return $this->respondWithValidationError('Validation errors', $this->validator->getErrors());
        }

        $data = [
            'member_username' => $this->request->getVar('member_username'),
            'member_email' => $this->request->getVar('member_email'),
            'member_full_name' => $this->request->getVar('member_full_name'),
            'member_address' => $this->request->getVar('member_address'),
            'member_job' => $this->request->getVar('member_job'),
            'member_status' => $this->request->getVar('member_status'),
            'member_religion' => $this->request->getVar('member_religion'),
            'member_barcode' => $this->request->getVar('member_barcode'),
            'member_gender' => $this->request->getVar('member_gender'),
        ];

        try {
            // Raw query untuk update data member
            $query = "UPDATE member SET member_username = ?, member_email = ?, member_full_name = ?, member_address = ?, member_job = ?, member_status = ?, member_religion = ?, member_barcode = ?, member_gender = ? 
                      WHERE member_id = ?";

            $db->query($query, [
                $data['member_username'],
                $data['member_email'],
                $data['member_full_name'],
                $data['member_address'],
                $data['member_job'],
                $data['member_status'],
                $data['member_religion'],
                $data['member_barcode'],
                $data['member_gender'],
                $id
            ]);

            return $this->respondWithSuccess('Member updated successfully.');
        } catch (DatabaseException $e) {
            return $this->respondWithError('Failed to update member: ' . $e->getMessage());
        }
    }

    // Fungsi untuk menghapus member berdasarkan member_id (Delete)
    public function delete($id = null)
    {
        $db = \Config\Database::connect();

        try {
            // Raw query untuk menghapus data member berdasarkan member_id
            $query = "DELETE FROM member WHERE member_id = ?";
            $db->query($query, [$id]);

            return $this->respondWithSuccess('Member deleted successfully.');
        } catch (DatabaseException $e) {
            return $this->respondWithError('Failed to delete member: ' . $e->getMessage());
        }
    }

    // Fungsi untuk mendapatkan semua member (List)


    // Fungsi untuk mendapatkan semua member dengan pagination, search, dan filter
    public function index()
    {
        $db = \Config\Database::connect();

        // Ambil parameter dari query string
        $limit = $this->request->getVar('limit') ?? 10; // Default limit 10
        $page = $this->request->getVar('page') ?? 1; // Default page 1
        $search = $this->request->getVar('search');
        $filter = $this->request->getVar('filter'); // Bisa berupa status atau job, misalnya

        // Hitung offset untuk pagination
        $offset = ($page - 1) * $limit;

        try {
            // Query dasar
            $query = "SELECT * FROM member";
            $conditions = [];
            $params = [];

            // Tambahkan filter dan pencarian jika ada
            if ($search) {
                $conditions[] = "(member_username LIKE ? OR member_full_name LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }

            if ($filter) {
                $conditions[] = "member_status = ?";
                $params[] = $filter;
            }

            if (count($conditions) > 0) {
                $query .= ' WHERE ' . implode(' AND ', $conditions);
            }

            // Tambahkan limit dan offset untuk pagination
            $query .= " LIMIT ? OFFSET ?";
            $params[] = (int)$limit;
            $params[] = (int)$offset;

            $members = $db->query($query, $params)->getResultArray();

            // Hitung total member untuk pagination
            $totalQuery = "SELECT COUNT(*) as total FROM member";
            if (count($conditions) > 0) {
                $totalQuery .= ' WHERE ' . implode(' AND ', $conditions);
            }
            $total = $db->query($totalQuery, $params)->getRow()->total;

            return $this->respondWithSuccess('Members retrieved successfully.', [
                'members' => $members,
                'total' => $total,
                'limit' => (int)$limit,
                'page' => (int)$page,
            ]);
        } catch (DatabaseException $e) {
            return $this->respondWithError('Failed to retrieve members: ' . $e->getMessage());
        }
    }
}


