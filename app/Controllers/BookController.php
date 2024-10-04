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

        // Ambil parameter dari query string
        $limit = $this->request->getVar('limit') ?? 10; // Default limit
        $page = $this->request->getVar('page') ?? 1; // Default page
        $search = $this->request->getVar('search');
        $filters = $this->request->getVar('filter') ?? []; // Get all filters

        // Hitung offset untuk pagination
        $offset = ($page - 1) * $limit;

        // Start building the query
        $query = "SELECT book_id, books_publisher_id, books_author_id, books_title, books_publication_year, books_isbn, books_stock_quantity, books_price, books_barcode FROM books";
        $conditions = [];
        $params = [];

        // Handle search condition
        if ($search) {
            $conditions[] = "(books_title LIKE ?)";
            $params[] = "%$search%"; // Prepare search parameter
        }

        // Define the mapping of filter keys to database columns
        $filterMapping = [
            'publisher_id' => 'books_publisher_id',
            'author_id' => 'books_author_id',
            'isbn' => 'books_isbn',
            'title' => 'books_title',
            'year' => 'books_publication_year',
            'stock_quantity' => 'books_stock_quantity',
            'price' => 'books_price',
            'barcode' => 'books_barcode'
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

        try {
            // Execute query to get book data
            $books = $db->query($query, $params)->getResultArray();

            $result = [];
            foreach ($books as $book) {
                $result[] = [
                    'id' => (int) $book['book_id'],
                    'publisher_id' => (int) $book['books_publisher_id'],
                    'author_id' => (int) $book['books_author_id'],
                    'title' => $book['books_title'],
                    'publication_year' => (int) $book['books_publication_year'],
                    'isbn' => $book['books_isbn'],
                    'stock_quantity' => (int) $book['books_stock_quantity'],
                    'price' => (float) $book['books_price'],
                    'barcode' => $book['books_barcode'],
                ];
            }

            // Query total books for pagination
            $totalQuery = "SELECT COUNT(*) as total FROM books";
            if (count($conditions) > 0) {
                $totalQuery .= ' WHERE ' . implode(' AND ', $conditions);
            }
            $total = $db->query($totalQuery, array_slice($params, 0, count($params) - 2))->getRow()->total; // Exclude LIMIT and OFFSET params

            // Calculate total pages
            $jumlah_page = ceil($total / $limit);

            // Calculate previous and next pages
            $prev = ($page > 1) ? $page - 1 : null;
            $next = ($page < $jumlah_page) ? $page + 1 : null;

            // Calculate start and end positions for pagination
            $start = ($page - 1) * $limit + 1;
            $end = min($page * $limit, $total);

            // Prepare pagination details
            $detail = range(max(1, $page - 2), min($jumlah_page, $page + 2));

            // Return response
            return $this->respondWithSuccess('Books retrieved successfully.', [
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


            $result = [
                'data' => [
                    'id' => (int) $book['book_id'],
                    'publisher_id' => (int) $book['books_publisher_id'],
                    'title' => $book['books_author_id'],
                    'publication_year' => $book['books_title'],
                    'isbn' => $book['books_publication_year'],
                    'stock_quantity' => (int) $book['books_stock_quantity'],
                    'author_id' => (int) $book['books_stock_quantity'],
                    'price' => (int) $book['books_price'],
                    'author' => (int) $book['books_barcode']

                ]
            ];

            return $this->respondWithSuccess('Book found.', $result);
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
