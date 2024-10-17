<?php

namespace App\Controllers;

use CodeIgniter\Database\Exceptions\DatabaseException;

class BookController extends AuthorizationController
{
    // Fungsi untuk menambahkan buku (Create)
    public function create()
    {
        $db = \Config\Database::connect();

        $tokenValidation = $this->validateToken('superadmin,warehouse'); // Fungsi helper dipanggil

        if ($tokenValidation !== true) {
            return $this->respond($tokenValidation, $tokenValidation['status']);
        }



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

        $tokenValidation = $this->validateToken('superadmin,warehouse,frontliner'); // Fungsi helper dipanggil

        if ($tokenValidation !== true) {
            return $this->respond($tokenValidation, $tokenValidation['status']);
        }

        // Ambil parameter dari query string
        $limit = $this->request->getVar('limit') ?? 10; // Default limit
        $page = $this->request->getVar('page') ?? 1; // Default page
        $search = $this->request->getVar('search');
        $filters = $this->request->getVar('filter') ?? []; // Get all filters
        $enablePagination = filter_var($this->request->getVar('pagination'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;

        // Hitung offset untuk pagination
        $offset = ($page - 1) * $limit;

        // Start building the query with JOIN to publisher and author tables
        $query = "SELECT books.book_id, books.books_publisher_id, books.books_author_id, books.books_title, books.books_publication_year, books.books_isbn, books.books_stock_quantity, books.books_price, books.books_barcode,
              publisher.publisher_name, publisher.publisher_address, publisher.publisher_phone, publisher.publisher_email,
              author.author_name, author.author_biography
              FROM books
              JOIN publisher ON books.books_publisher_id = publisher.publisher_id
              JOIN author ON books.books_author_id = author.author_id";
        $conditions = [];
        $params = [];

        // Handle search condition (mencakup semua kolom yang relevan, termasuk author dan publisher)
        if ($search) {
            $conditions[] = "(books.books_title LIKE ? OR books.books_isbn LIKE ? OR books.books_barcode LIKE ? 
                        OR publisher.publisher_name LIKE ? OR author.author_name LIKE ?)";
            $searchTerm = "%" . $search . "%";
            $params = array_fill(0, 5, $searchTerm); // Isi parameter search untuk semua kolom yang diinginkan
        }

        // Define the mapping of filter keys to database columns, including publisher and author fields
        $filterMapping = [
            'publisher_name' => 'publisher.publisher_name',
            'publisher_address' => 'publisher.publisher_address',
            'publisher_phone' => 'publisher.publisher_phone',
            'publisher_email' => 'publisher.publisher_email',
            'author_name' => 'author.author_name',
            'author_biography' => 'author.author_biography',
            'isbn' => 'books.books_isbn',
            'title' => 'books.books_title',
            'year' => 'books.books_publication_year',
            'stock_quantity' => 'books.books_stock_quantity',
            'price' => 'books.books_price',
            'barcode' => 'books.books_barcode'
        ];

        // Handle additional filters, including publisher and author filters
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

        // Add limit and offset for pagination (hanya jika pagination diaktifkan)
        if ($enablePagination) {
            $query .= " LIMIT ? OFFSET ?";
            $params[] = (int) $limit;
            $params[] = (int) $offset;
        }

        try {
            // Execute query to get book data with publisher and author details
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
                    'publisher_name' => $book['publisher_name'],
                    'publisher_address' => $book['publisher_address'],
                    'publisher_phone' => $book['publisher_phone'],
                    'publisher_email' => $book['publisher_email'],
                    'author_name' => $book['author_name'],
                    'author_biography' => $book['author_biography']
                ];
            }

            if ($enablePagination) {
                // Query total books for pagination
                $totalQuery = "SELECT COUNT(*) as total FROM books
                           JOIN publisher ON books.books_publisher_id = publisher.publisher_id
                           JOIN author ON books.books_author_id = author.author_id";
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

                return $this->respondWithSuccess('Berhasil mendapatkan data buku.', [
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
            } else {
                // Jika pagination dinonaktifkan, hanya kembalikan data tanpa pagination
                return $this->respondWithSuccess('Berhasil mendapatkan data buku.', ['data' => $result]);
            }
        } catch (DatabaseException $e) {
            return $this->respondWithError('Terdapat kesalahan di sisi server: ' . $e->getMessage());
        }
    }





    // Fungsi untuk mendapatkan buku berdasarkan ID (Read)
    public function get_detail()
    {
        $db = \Config\Database::connect();

        $id = $this->request->getVar('id'); // Get ID from query parameter


        if (!$id) {
            return $this->respondWithValidationError('Parameter ID is required.', );
        }

        $tokenValidation = $this->validateToken('superadmin,warehouse,frontliner'); // Fungsi helper dipanggil

        if ($tokenValidation !== true) {
            return $this->respond($tokenValidation, $tokenValidation['status']);
        }

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

        $tokenValidation = $this->validateToken('superadmin,warehouse'); // Fungsi helper dipanggil

        if ($tokenValidation !== true) {
            return $this->respond($tokenValidation, $tokenValidation['status']);
        }

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
    public function delete_book()
    {
        $db = \Config\Database::connect();
        $id = $this->request->getVar(index: 'id'); // Default limit = 10


        $tokenValidation = $this->validateToken('superadmin,warehouse'); // Fungsi helper dipanggil

        if ($tokenValidation !== true) {
            return $this->respond($tokenValidation, $tokenValidation['status']);
        }

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
