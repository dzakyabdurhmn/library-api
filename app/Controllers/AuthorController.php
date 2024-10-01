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

        $limit = $this->request->getVar('limit') ?? 10;
        $page = $this->request->getVar('page') ?? 1;
        $search = $this->request->getVar('search');

        $offset = ($page - 1) * $limit;

        try {
            $query = "SELECT * FROM author";
            $conditions = [];
            $params = [];

            if ($search) {
                $conditions[] = "author_name LIKE ?";
                $params[] = "%$search%";
            }

            if (count($conditions) > 0) {
                $query .= ' WHERE ' . implode(' AND ', $conditions);
            }

            $query .= " LIMIT ? OFFSET ?";
            $params[] = (int) $limit;
            $params[] = (int) $offset;

            $authors = $db->query($query, $params)->getResultArray();



            $result = [];
            foreach ($authors as $author) {
                $result[] = [
                    'id' => $author['author_id'],
                    'name' => $author['author_name'],
                    'biography' => $author['author_biography'],
                ];
            }


            // Hitung total penulis untuk pagination
            $totalQuery = "SELECT COUNT(*) as total FROM author";
            if (count($conditions) > 0) {
                $totalQuery .= ' WHERE ' . implode(' AND ', $conditions);
            }
            $total = $db->query($totalQuery, $params)->getRow()->total;

            return $this->respondWithSuccess('author retrieved successfully.', [
                'data' => $result,
                'total' => $total,
                'limit' => (int) $limit,
                'page' => (int) $page,
            ]);
        } catch (DatabaseException $e) {
            return $this->respondWithError('Failed to retrieve author: ' . $e->getMessage());
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
