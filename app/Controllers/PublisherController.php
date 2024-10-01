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

        // Ambil parameter limit, page, search, dan filter
        $limit = $this->request->getVar('limit') ?? 10;
        $page = $this->request->getVar('page') ?? 1;
        $search = $this->request->getVar('search');
        $filters = $this->request->getVar('filter') ?? []; // Filter array

        $offset = ($page - 1) * $limit;

        try {
            $query = "SELECT * FROM publisher";
            $conditions = [];
            $params = [];

            // Handle search untuk nama penerbit
            if ($search) {
                $conditions[] = "publisher_name LIKE ?";
                $params[] = "%$search%";
            }

            // Map filter dari client ke nama field di database
            $filterMapping = [
                'name' => 'publisher_name',
                'address' => 'publisher_address',
                'phone' => 'publisher_phone',
                'email' => 'publisher_email',
            ];

            // Handle filtering berdasarkan array filter
            if (!empty($filters)) {
                foreach ($filters as $key => $value) {
                    if (array_key_exists($key, $filterMapping)) {
                        $dbField = $filterMapping[$key];  // Ambil nama field yang sesuai di database
                        if (is_array($value)) {
                            // Jika value berupa array (misalnya range atau multiple values)
                            $conditions[] = "$dbField IN (" . implode(',', array_fill(0, count($value), '?')) . ")";
                            $params = array_merge($params, $value);
                        } else {
                            // Jika value single value
                            $conditions[] = "$dbField = ?";
                            $params[] = $value;
                        }
                    }
                }
            }

            // Jika ada kondisi, tambahkan WHERE ke query
            if (count($conditions) > 0) {
                $query .= ' WHERE ' . implode(' AND ', $conditions);
            }

            // Tambahkan LIMIT dan OFFSET ke query
            $query .= " LIMIT ? OFFSET ?";
            $params[] = (int) $limit;
            $params[] = (int) $offset;

            // Jalankan query dan ambil hasil
            $publishers = $db->query($query, $params)->getResultArray();



            $result = [];
            foreach ($publishers as $publisher) {
                $result[] = [

                    'id' => (int) $publisher['publisher_id'],
                    'name' => $publisher['publisher_name'],
                    'address' => $publisher['publisher_address'],
                    'phone' => $publisher['publisher_phone'],
                    'email' => $publisher['publisher_email']

                ];
            }


            // Hitung total penerbit untuk pagination
            $totalQuery = "SELECT COUNT(*) as total FROM publisher";
            if (count($conditions) > 0) {
                $totalQuery .= ' WHERE ' . implode(' AND ', $conditions);
            }

            // Hitung total tanpa limit dan offset
            $total = $db->query($totalQuery, array_slice($params, 0, -2))->getRow()->total;

            // Kembalikan hasil dengan pagination
            return $this->respondWithSuccess('Publisher retrieved successfully.', [
                'data' => $result,
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

            $result = [
                'id' => $publisher['publisher_id'],
                'name' => $publisher['publisher_name'],
                'address' => $publisher['publisher_address'],
                'phone' => $publisher['publisher_phone'],
                'email' => $publisher['publisher_email'],
            ];

            if (!$publisher) {
                return $this->respondWithNotFound('Publisher not found.');
            }

            return $this->respondWithSuccess('Publisher found.', $result);
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


// https://chatgpt.com/c/66f6315e-78ac-8013-9a5e-1a6c55ed5f37