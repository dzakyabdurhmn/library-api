<?php

namespace App\Controllers;

use CodeIgniter\Database\Exceptions\DatabaseException;

class BookController extends CoreController
{
    // Fungsi untuk menambahkan buku (Create)
    public function create()
    {
        $db = \Config\Database::connect();

        $rules = [
            'publisher_id' => 'required|integer',
            'author_id' => 'required|integer',
            'title' => 'required|min_length[1]',
            'publication_year' => 'integer',
            'isbn' => 'required|integer', // Validasi jika ada
            'stock_quantity' => 'required|integer',
            'price' => 'required|decimal',
            'barcode' => 'required|min_length[1]'
        ];

        if (!$this->validate($rules)) {
            return $this->respondWithValidationError('Validation errors', $this->validator->getErrors());
        }

        $data = [
            'books_publisher_id' => $this->request->getVar('publisher_id'),
            'books_author_id' => $this->request->getVar('author_id'),
            'books_title' => $this->request->getVar('title'),
            'books_publication_year' => $this->request->getVar('publication_year'),
            'books_isbn' => $this->request->getVar('isbn'),
            'books_stock_quantity' => $this->request->getVar('stock_quantity'),
            'books_price' => $this->request->getVar('price'),
            'books_barcode' => $this->request->getVar('barcode'),
        ];

        // Cek apakah author_id valid
        $authorExists = $db->query("SELECT COUNT(*) as count FROM author WHERE author_id = ?", [$data['books_author_id']])->getRow()->count;

        if ($authorExists == 0) {
            return $this->respondWithError('Failed to add book: Author not found.', null, 404);
        }

        // Cek apakah publisher_id valid
        $publisherExists = $db->query("SELECT COUNT(*) as count FROM publisher WHERE publisher_id = ?", [$data['books_publisher_id']])->getRow()->count;

        if ($publisherExists == 0) {
            return $this->respondWithError('Failed to add book: Publisher not found.', null, 404);
        }

        try {
            $query = "INSERT INTO books (books_publisher_id, books_author_id, books_title, books_publication_year, books_isbn, books_stock_quantity, books_price, books_barcode) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

            $db->query($query, array_values($data));

            return $this->respondWithSuccess('Book added successfully.');
        } catch (DatabaseException $e) {
            return $this->respondWithError('Failed to add book: ' . $e->getMessage());
        }
    }

    // Fungsi untuk mendapatkan semua buku dengan pagination, search, dan filter (Read)
    public function index()
    {
        $db = \Config\Database::connect();

        $limit = $this->request->getVar('limit') ?? 10;
        $page = $this->request->getVar('page') ?? 1;
        $search = $this->request->getVar('search');
        $filter = $this->request->getVar('filter'); // Misalnya filter berdasarkan publisher atau author

        $offset = ($page - 1) * $limit;

        try {
            $query = "SELECT * FROM books";
            $conditions = [];
            $params = [];

            if ($search) {
                $conditions[] = "books_title LIKE ?";
                $params[] = "%$search%";
            }

            if ($filter) {
                $conditions[] = "books_publisher_id = ?";
                $params[] = $filter;
            }

            if (count($conditions) > 0) {
                $query .= ' WHERE ' . implode(' AND ', $conditions);
            }

            $query .= " LIMIT ? OFFSET ?";
            $params[] = (int) $limit;
            $params[] = (int) $offset;

            $books = $db->query($query, $params)->getResultArray();

            // Hitung total buku untuk pagination
            $totalQuery = "SELECT COUNT(*) as total FROM books";
            if (count($conditions) > 0) {
                $totalQuery .= ' WHERE ' . implode(' AND ', $conditions);
            }
            $total = $db->query($totalQuery, $params)->getRow()->total;

            return $this->respondWithSuccess('Books retrieved successfully.', [
                'books' => $books,
                'total' => $total,
                'limit' => (int) $limit,
                'page' => (int) $page,
            ]);
        } catch (DatabaseException $e) {
            return $this->respondWithError('Failed to retrieve books: ' . $e->getMessage());
        }
    }

    // Fungsi untuk mendapatkan buku berdasarkan ID (Read)
    public function show($id = null)
    {
        $db = \Config\Database::connect();

        try {
            $query = "SELECT * FROM books WHERE book_id = ?";
            $book = $db->query($query, [$id])->getRowArray();

            if (!$book) {
                return $this->respondWithNotFound('Book not found.');
            }

            return $this->respondWithSuccess('Book found.', $book);
        } catch (DatabaseException $e) {
            return $this->respondWithError('Failed to retrieve book: ' . $e->getMessage());
        }
    }

    // Fungsi untuk memperbarui data buku (Update)
    public function update($id = null)
    {
        $db = \Config\Database::connect();

        // Aturan validasi data yang akan diubah
        $rules = [
            'publisher_id' => 'permit_empty|integer',
            'author_id' => 'permit_empty|integer',
            'title' => 'permit_empty|min_length[1]',
            'publication_year' => 'permit_empty|integer',
            'isbn' => 'permit_empty|integer',
            'stock_quantity' => 'permit_empty|integer',
            'price' => 'permit_empty|decimal',
            'barcode' => 'permit_empty|min_length[1]'
        ];

        if (!$this->validate($rules)) {
            return $this->respondWithValidationError('Validation errors', $this->validator->getErrors());
        }

        // Cek apakah buku dengan ID tersebut ada
        $query = "SELECT * FROM books WHERE book_id = ?";
        $book = $db->query($query, [$id])->getRowArray();

        if (!$book) {
            return $this->respondWithError('Failed to update book: Book not found.', null, 404);
        }

        // Ambil data dari request
        $data = [
            'books_publisher_id' => $this->request->getVar('publisher_id'),
            'books_author_id' => $this->request->getVar('author_id'),
            'books_title' => $this->request->getVar('title'),
            'books_publication_year' => $this->request->getVar('publication_year'),
            'books_isbn' => $this->request->getVar('isbn'),
            'books_stock_quantity' => $this->request->getVar('stock_quantity'),
            'books_price' => $this->request->getVar('price'),
            'books_barcode' => $this->request->getVar('barcode'),
        ];

        // Cek minimal satu kolom yang diupdate
        if (empty(array_filter($data))) {
            return $this->respondWithError('Failed to update book: At least one field must be provided.', null, 400);
        }

        try {
            // Update query
            $query = "UPDATE books SET 
                    books_publisher_id = COALESCE(?, books_publisher_id), 
                    books_author_id = COALESCE(?, books_author_id), 
                    books_title = COALESCE(?, books_title), 
                    books_publication_year = COALESCE(?, books_publication_year), 
                    books_isbn = COALESCE(?, books_isbn), 
                    books_stock_quantity = COALESCE(?, books_stock_quantity), 
                    books_price = COALESCE(?, books_price), 
                    books_barcode = COALESCE(?, books_barcode) 
                  WHERE book_id = ?";

            $db->query($query, array_merge(array_values($data), [$id]));

            return $this->respondWithSuccess('Book updated successfully.', $data);
        } catch (DatabaseException $e) {
            return $this->respondWithError('Failed to update book: ' . $e->getMessage());
        }
    }


    // Fungsi untuk menghapus buku (Delete)
    public function delete($id = null)
    {
        $db = \Config\Database::connect();

        try {
            // Cek apakah buku dengan ID tersebut ada
            $query = "SELECT COUNT(*) as count FROM books WHERE book_id = ?";
            $exists = $db->query($query, [$id])->getRow()->count;

            if ($exists == 0) {
                return $this->respondWithError('Failed to delete book: Book not found.', null, 404);
            }

            // Lakukan penghapusan data
            $query = "DELETE FROM books WHERE book_id = ?";
            $db->query($query, [$id]);

            return $this->respondWithSuccess('Book deleted successfully.');
        } catch (DatabaseException $e) {
            return $this->respondWithError('Failed to delete book: ' . $e->getMessage());
        }
    }

}
