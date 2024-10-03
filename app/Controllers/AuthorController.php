<?php

namespace App\Controllers;

use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\API\ResponseTrait;


class AuthorController extends CoreController
{





    function validateToken($token)
    {
        $db = \Config\Database::connect();

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




    // Fungsi untuk menambahkan penulis (Create)
    public function create()
    {
        $db = \Config\Database::connect();

        // helper('TokenHelper');

        // Ambil token dari header Authorization
        // $authHeader = $this->request->getHeader('Authorization');
        // $token = null;

        // if ($authHeader) {
        //     $token = str_replace('Bearer ', '', $authHeader->getValue());
        // }

        // // Validasi token
        // $tokenValidation = $this->validateToken($token); // Fungsi helper dipanggil
        // if ($tokenValidation !== true) {
        //     return $this->respond($tokenValidation, $tokenValidation['status']);
        // }

        // Validasi input
        $rules = [
            'author_name' => 'required|min_length[1]',
            'author_biography' => 'permit_empty'
        ];

        if (!$this->validate($rules)) {
            return $this->respondWithValidationError('Validation errors', $this->validator->getErrors());
        }

        // Ambil data dari request
        $data = [
            'author_name' => $this->request->getVar('author_name'),
            'author_biography' => $this->request->getVar('author_biography')
        ];

        try {
            // Query untuk insert data ke tabel author
            $query = "INSERT INTO author (author_name, author_biography) VALUES (?, ?)";
            $db->query($query, array_values($data));

            return $this->respondWithSuccess('Author added successfully.');
        } catch (\Exception $e) {
            return $this->respondWithError('Failed to add author: ' . $e->getMessage());
        }
    }

    // Fungsi untuk mendapatkan semua penulis dengan pagination, search, dan filter (Read)

    public function index()
    {
        $db = \Config\Database::connect();

        // Get parameters from query string
        $limit = $this->request->getVar('limit') ?? 10;
        $page = $this->request->getVar('page') ?? 1;
        $search = $this->request->getVar('search');
        $filters = $this->request->getVar('filter') ?? []; // Get all filters

        // Calculate offset for pagination
        $offset = ($page - 1) * $limit;

        try {
            // Start building the query
            $query = "SELECT * FROM author";
            $conditions = [];
            $params = [];

            // Handle search condition
            if ($search) {
                $conditions[] = "author_name LIKE ?";
                $params[] = "%$search%"; // Prepare search parameter
            }

            // Define mapping for additional filters if needed
            $filterMapping = [
                'name' => 'author_name',
                // Add other mappings as necessary
            ];

            // Handle additional filters
            foreach ($filters as $key => $value) {
                if (!empty($value) && array_key_exists($key, $filterMapping)) {
                    $conditions[] = "{$filterMapping[$key]} = ?";
                    $params[] = $value;
                }
            }

            // Add conditions to the query
            if (count($conditions) > 0) {
                $query .= ' WHERE ' . implode(' AND ', $conditions);
            }

            // Add limit and offset for pagination
            $query .= " LIMIT ? OFFSET ?";
            $params[] = (int) $limit;
            $params[] = (int) $offset;

            // Execute query to get author data
            $authors = $db->query($query, $params)->getResultArray();

            // Format the result
            $result = [];
            foreach ($authors as $author) {
                $result[] = [
                    'id' => (int) $author['author_id'],
                    'name' => $author['author_name'],
                    'biography' => $author['author_biography'],
                ];
            }

            // Query total authors for pagination
            $totalQuery = "SELECT COUNT(*) as total FROM author";
            if (count($conditions) > 0) {
                $totalQuery .= ' WHERE ' . implode(' AND ', $conditions);
            }
            $total = $db->query($totalQuery, array_slice($params, 0, count($params) - 2))->getRow()->total; // Exclude LIMIT and OFFSET params

            // Calculate pagination details
            $jumlah_page = ceil($total / $limit);
            $prev = ($page > 1) ? $page - 1 : null;
            $next = ($page < $jumlah_page) ? $page + 1 : null;
            $start = ($page - 1) * $limit + 1;
            $end = min($page * $limit, $total);
            $detail = range(max(1, $page - 2), min($jumlah_page, $page + 2));

            // Return response
            return $this->respondWithSuccess('Authors retrieved successfully.', [
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
            return $this->respondWithError('Failed to retrieve authors: ' . $e->getMessage());
        }
    }



    // Fungsi untuk mendapatkan penulis berdasarkan ID (Read)
    public function show($id = null)
    {
        $db = \Config\Database::connect();

        try {
            $query = "SELECT * FROM author WHERE author_id = ?";
            $author = $db->query($query, [$id])->getRowArray();

            if (!$author) {
                return $this->respondWithNotFound('Author not found.');
            }


            $data = [
                'data' => [
                    'id' => $author['author_id'],
                    'name' => $author['author_name'],
                    'biography' => 'author_biography'
                ],
            ];

            return $this->respondWithSuccess('Author found.', $data);
        } catch (DatabaseException $e) {
            return $this->respondWithError('Failed to retrieve author: ' . $e->getMessage());
        }
    }

    // Fungsi untuk memperbarui data penulis (Update)
    public function update($id = null)
    {
        $db = \Config\Database::connect();

        $rules = [
            'author_name' => 'permit_empty|min_length[1]',
            'author_biography' => 'permit_empty'
        ];

        if (!$this->validate($rules)) {
            return $this->respondWithValidationError('Validation errors', $this->validator->getErrors());
        }

        // Cek apakah penulis dengan ID tersebut ada
        $query = "SELECT COUNT(*) as count FROM author WHERE author_id = ?";
        $exists = $db->query($query, [$id])->getRow()->count;

        if ($exists == 0) {
            return $this->respondWithError('Failed to update author: Author not found.', null, 404);
        }

        $data = [
            'author_name' => $this->request->getVar('author_name'),
            'author_biography' => $this->request->getVar('author_biography')
        ];

        try {
            $query = "UPDATE author SET 
                      author_name = COALESCE(?, author_name), 
                      author_biography = COALESCE(?, author_biography) 
                      WHERE author_id = ?";

            $db->query($query, array_merge(array_values($data), [$id]));

            return $this->respondWithSuccess('Author updated successfully.');
        } catch (DatabaseException $e) {
            return $this->respondWithError('Failed to update author: ' . $e->getMessage());
        }
    }

    // Fungsi untuk menghapus penulis (Delete)
    public function delete($id = null)
    {
        $db = \Config\Database::connect();

        try {
            // Cek apakah penulis dengan ID tersebut ada
            $query = "SELECT COUNT(*) as count FROM author WHERE author_id = ?";
            $exists = $db->query($query, [$id])->getRow()->count;

            if ($exists == 0) {
                return $this->respondWithError('Failed to delete author: Author not found.', null, 404);
            }

            // Cek apakah penulis sedang digunakan di tabel buku
            $bookCount = $db->query("SELECT COUNT(*) as count FROM books WHERE books_author_id = ?", [$id])->getRow()->count;

            if ($bookCount > 0) {
                return $this->respondWithError('Failed to delete author: This author is currently associated with books and cannot be deleted.', null, 400);
            }

            // Lakukan penghapusan data
            $query = "DELETE FROM author WHERE author_id = ?";
            $db->query($query, [$id]);

            return $this->respondWithSuccess('Author deleted successfully.');
        } catch (DatabaseException $e) {
            return $this->respondWithError('Failed to delete author: ' . $e->getMessage());
        }
    }

}
