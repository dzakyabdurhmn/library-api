<?php

namespace App\Controllers;

use CodeIgniter\Database\Exceptions\DatabaseException;

class AuthorController extends CoreController
{
    // Fungsi untuk menambahkan penulis (Create)
    public function create()
    {
        $db = \Config\Database::connect();

        $rules = [
            'author_name' => 'required|min_length[1]',
            'author_biography' => 'permit_empty'
        ];

        if (!$this->validate($rules)) {
            return $this->respondWithValidationError('Validation errors', $this->validator->getErrors());
        }

        $data = [
            'author_name' => $this->request->getVar('author_name'),
            'author_biography' => $this->request->getVar('author_biography')
        ];

        try {
            $query = "INSERT INTO author (author_name, author_biography) VALUES (?, ?)";
            $db->query($query, array_values($data));

            return $this->respondWithSuccess('Author added successfully.');
        } catch (DatabaseException $e) {
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

            $author = $db->query($query, $params)->getResultArray();

            // Hitung total penulis untuk pagination
            $totalQuery = "SELECT COUNT(*) as total FROM author";
            if (count($conditions) > 0) {
                $totalQuery .= ' WHERE ' . implode(' AND ', $conditions);
            }
            $total = $db->query($totalQuery, $params)->getRow()->total;

            return $this->respondWithSuccess('author retrieved successfully.', [
                'author' => $author,
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

            return $this->respondWithSuccess('Author found.', $author);
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
