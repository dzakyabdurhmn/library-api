<?php

namespace App\Controllers;

use CodeIgniter\Database\Exceptions\DatabaseException;

class PublisherController extends CoreController
{
    // Fungsi untuk menambahkan penerbit (Create)
    public function create()
    {
        $db = \Config\Database::connect();

        $rules = [
            'publisher_name' => 'required|min_length[1]',
            'publisher_address' => 'permit_empty|min_length[1]',
            'publisher_phone' => 'permit_empty|min_length[1]',
            'publisher_email' => 'permit_empty|valid_email'
        ];

        if (!$this->validate($rules)) {
            return $this->respondWithValidationError('Validation errors', $this->validator->getErrors());
        }

        $data = [
            'publisher_name' => $this->request->getVar('publisher_name'),
            'publisher_address' => $this->request->getVar('publisher_address'),
            'publisher_phone' => $this->request->getVar('publisher_phone'),
            'publisher_email' => $this->request->getVar('publisher_email')
        ];

        try {
            $query = "INSERT INTO publisher (publisher_name, publisher_address, publisher_phone, publisher_email) VALUES (?, ?, ?, ?)";
            $db->query($query, array_values($data));

            return $this->respondWithSuccess('Publisher added successfully.');
        } catch (DatabaseException $e) {
            return $this->respondWithError('Failed to add publisher: ' . $e->getMessage());
        }
    }

    // Fungsi untuk mendapatkan semua penerbit dengan pagination, search, dan filter (Read)
    public function index()
    {
        $db = \Config\Database::connect();

        $limit = $this->request->getVar('limit') ?? 10;
        $page = $this->request->getVar('page') ?? 1;
        $search = $this->request->getVar('search');

        $offset = ($page - 1) * $limit;

        try {
            $query = "SELECT * FROM publisher";
            $conditions = [];
            $params = [];

            if ($search) {
                $conditions[] = "publisher_name LIKE ?";
                $params[] = "%$search%";
            }

            if (count($conditions) > 0) {
                $query .= ' WHERE ' . implode(' AND ', $conditions);
            }

            $query .= " LIMIT ? OFFSET ?";
            $params[] = (int) $limit;
            $params[] = (int) $offset;

            $publisher = $db->query($query, $params)->getResultArray();

            // Hitung total penerbit untuk pagination
            $totalQuery = "SELECT COUNT(*) as total FROM publisher";
            if (count($conditions) > 0) {
                $totalQuery .= ' WHERE ' . implode(' AND ', $conditions);
            }
            $total = $db->query($totalQuery, $params)->getRow()->total;

            return $this->respondWithSuccess('publisher retrieved successfully.', [
                'publisher' => $publisher,
                'total' => $total,
                'limit' => (int) $limit,
                'page' => (int) $page,
            ]);
        } catch (DatabaseException $e) {
            return $this->respondWithError('Failed to retrieve publisher: ' . $e->getMessage());
        }
    }

    // Fungsi untuk mendapatkan penerbit berdasarkan ID (Read)
    public function show($id = null)
    {
        $db = \Config\Database::connect();

        try {
            $query = "SELECT * FROM publisher WHERE publisher_id = ?";
            $publisher = $db->query($query, [$id])->getRowArray();

            if (!$publisher) {
                return $this->respondWithNotFound('Publisher not found.');
            }

            return $this->respondWithSuccess('Publisher found.', $publisher);
        } catch (DatabaseException $e) {
            return $this->respondWithError('Failed to retrieve publisher: ' . $e->getMessage());
        }
    }

    // Fungsi untuk memperbarui data penerbit (Update)
    public function update($id = null)
    {
        $db = \Config\Database::connect();

        $rules = [
            'publisher_name' => 'permit_empty|min_length[1]',
            'publisher_address' => 'permit_empty|min_length[1]',
            'publisher_phone' => 'permit_empty|min_length[1]',
            'publisher_email' => 'permit_empty|valid_email'
        ];

        if (!$this->validate($rules)) {
            return $this->respondWithValidationError('Validation errors', $this->validator->getErrors());
        }

        // Cek apakah penerbit dengan ID tersebut ada
        $query = "SELECT COUNT(*) as count FROM publisher WHERE publisher_id = ?";
        $exists = $db->query($query, [$id])->getRow()->count;

        if ($exists == 0) {
            return $this->respondWithError('Failed to update publisher: Publisher not found.', null, 404);
        }

        $data = [
            'publisher_name' => $this->request->getVar('publisher_name'),
            'publisher_address' => $this->request->getVar('publisher_address'),
            'publisher_phone' => $this->request->getVar('publisher_phone'),
            'publisher_email' => $this->request->getVar('publisher_email')
        ];

        try {
            $query = "UPDATE publisher SET 
                      publisher_name = COALESCE(?, publisher_name), 
                      publisher_address = COALESCE(?, publisher_address), 
                      publisher_phone = COALESCE(?, publisher_phone), 
                      publisher_email = COALESCE(?, publisher_email) 
                      WHERE publisher_id = ?";

            $db->query($query, array_merge(array_values($data), [$id]));

            return $this->respondWithSuccess('Publisher updated successfully.');
        } catch (DatabaseException $e) {
            return $this->respondWithError('Failed to update publisher: ' . $e->getMessage());
        }
    }

    // Fungsi untuk menghapus penerbit (Delete)
    public function delete($id = null)
    {
        $db = \Config\Database::connect();

        try {
            // Cek apakah penerbit dengan ID tersebut ada
            $query = "SELECT COUNT(*) as count FROM publisher WHERE publisher_id = ?";
            $exists = $db->query($query, [$id])->getRow()->count;

            if ($exists == 0) {
                return $this->respondWithError('Failed to delete publisher: Publisher not found.', null, 404);
            }

            // Cek apakah penerbit sedang digunakan di tabel buku
            $bookCount = $db->query("SELECT COUNT(*) as count FROM books WHERE books_publisher_id = ?", [$id])->getRow()->count;

            if ($bookCount > 0) {
                return $this->respondWithError('Failed to delete publisher: This publisher is currently associated with books and cannot be deleted.', null, 400);
            }

            // Lakukan penghapusan data
            $query = "DELETE FROM publisher WHERE publisher_id = ?";
            $db->query($query, [$id]);

            return $this->respondWithSuccess('Publisher deleted successfully.');
        } catch (DatabaseException $e) {
            return $this->respondWithError('Failed to delete publisher: ' . $e->getMessage());
        }
    }
}
