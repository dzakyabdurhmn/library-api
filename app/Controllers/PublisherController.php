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

        // Get parameters from query string
        $limit = $this->request->getVar('limit') ?? 10;
        $page = $this->request->getVar('page') ?? 1;
        $search = $this->request->getVar('search');
        $filters = $this->request->getVar('filter') ?? []; // Filter array

        // Calculate offset for pagination
        $offset = ($page - 1) * $limit;

        try {
            // Start building the query
            $query = "SELECT publisher_id, publisher_name, publisher_address, publisher_phone, publisher_email FROM publisher";
            $conditions = [];
            $params = [];

            // Handle search across multiple fields
            if ($search) {
                $conditions[] = "(publisher_name LIKE ? OR publisher_address LIKE ? OR publisher_phone LIKE ? OR publisher_email LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }

            // Map filter from client to database field names
            $filterMapping = [
                'name' => 'publisher_name',
                'address' => 'publisher_address',
                'phone' => 'publisher_phone',
                'email' => 'publisher_email',
            ];

            // Handle filtering based on the filter array
            foreach ($filters as $key => $value) {
                if (array_key_exists($key, $filterMapping)) {
                    $dbField = $filterMapping[$key];  // Get the corresponding field name in the database
                    if (is_array($value)) {
                        // If value is an array (e.g., range or multiple values)
                        $conditions[] = "$dbField IN (" . implode(',', array_fill(0, count($value), '?')) . ")";
                        $params = array_merge($params, $value);
                    } else {
                        // If single value
                        $conditions[] = "$dbField = ?";
                        $params[] = $value;
                    }
                }
            }

            // If there are conditions, add WHERE to the query
            if (count($conditions) > 0) {
                $query .= ' WHERE ' . implode(' AND ', $conditions);
            }

            // Add LIMIT and OFFSET to the query
            $query .= " LIMIT ? OFFSET ?";
            $params[] = (int) $limit;
            $params[] = (int) $offset;

            // Execute the query and get results
            $publishers = $db->query($query, $params)->getResultArray();

            // Format the result
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

            // Query total publishers for pagination
            $totalQuery = "SELECT COUNT(*) as total FROM publisher";
            if (count($conditions) > 0) {
                $totalQuery .= ' WHERE ' . implode(' AND ', $conditions);
            }

            // Calculate total without limit and offset
            $total = $db->query($totalQuery, array_slice($params, 0, count($params) - 2))->getRow()->total;

            // Calculate pagination details
            $jumlah_page = ceil($total / $limit);
            $prev = ($page > 1) ? $page - 1 : null;
            $next = ($page < $jumlah_page) ? $page + 1 : null;
            $start = ($page - 1) * $limit + 1;
            $end = min($page * $limit, $total);
            $detail = range(max(1, $page - 2), min($jumlah_page, $page + 2));

            // Return response
            return $this->respondWithSuccess('Publisher retrieved successfully.', [
                'data' => $result,
                'pagination' => [
                    'total_data' => (int) $total,
                    'jumlah_page' => (int) $jumlah_page,
                    'prev' => $prev,
                    'page' => (int) $page,
                    'next' => $next,
                    'detail' => $detail,
                    'start' => $start,
                    'end' => $end,
                ]
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