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
            'publisher_id' => [
                'rules' => 'required|is_natural_no_zero',
                'errors' => [
                    'required' => 'ID penerbit wajib diisi.',
                    'is_natural_no_zero' => 'ID penerbit harus berupa angka positif.'
                ]
            ],
            'author_id' => [
                'rules' => 'required|is_natural_no_zero',
                'errors' => [
                    'required' => 'ID penulis wajib diisi.',
                    'is_natural_no_zero' => 'ID penulis harus berupa angka positif.'
                ]
            ],
            'title' => [
                'rules' => 'required|min_length[1]',
                'errors' => [
                    'required' => 'Judul buku wajib diisi.',
                    'min_length' => 'Judul buku harus minimal 1 karakter.'
                ]
            ],
            'publication_year' => [
                'rules' => 'integer',
                'errors' => [
                    'integer' => 'Tahun terbit harus berupa angka.'
                ]
            ],
            'isbn' => [
                'rules' => 'required|integer',
                'errors' => [
                    'required' => 'ISBN wajib diisi.',
                    'integer' => 'ISBN harus berupa angka.'
                ]
            ],
            'stock_quantity' => [
                'rules' => 'required|integer',
                'errors' => [
                    'required' => 'Jumlah stok wajib diisi.',
                    'integer' => 'Jumlah stok harus berupa angka.'
                ]
            ],
            'price' => [
                'rules' => 'required|decimal',
                'errors' => [
                    'required' => 'Harga wajib diisi.',
                    'decimal' => 'Harga harus berupa angka desimal.'
                ]
            ],
            'barcode' => [
                'rules' => 'required|min_length[1]',
                'errors' => [
                    'required' => 'Barcode wajib diisi.',
                    'min_length' => 'Barcode harus minimal 1 karakter.'
                ]
            ]
        ];

        if (!$this->validate($rules)) {
            return $this->respondWithValidationError('Validasi error', $this->validator->getErrors());
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

        $tokenValidation = $this->validateToken('superadmin,warehouse'); // Fungsi helper dipanggil

        if ($tokenValidation !== true) {
            return $this->respond($tokenValidation, $tokenValidation['status']);
        }

        $limit = $this->request->getVar('limit') ?? 10;
        $page = $this->request->getVar('page') ?? 1;
        $search = $this->request->getVar('search');
        $filters = $this->request->getVar('filter') ?? [];
        $enablePagination = filter_var($this->request->getVar('pagination'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
        $sort = $this->request->getVar('sort') ?? '';


        $offset = ($page - 1) * $limit;

        $query = "SELECT books.book_id, books.books_publisher_id, books.books_author_id, books.books_title, books.books_publication_year, books.books_isbn, books.books_stock_quantity, books.books_price, books.books_barcode,
          publisher.publisher_name, publisher.publisher_address, publisher.publisher_phone, publisher.publisher_email,
          author.author_name, author.author_biography
          FROM books
          JOIN publisher ON books.books_publisher_id = publisher.publisher_id
          JOIN author ON books.books_author_id = author.author_id";
        $conditions = [];
        $params = [];

        if ($search) {
            $conditions[] = "(books.books_title LIKE ? OR books.books_isbn LIKE ? OR books.books_barcode LIKE ? 
                    OR publisher.publisher_name LIKE ? OR author.author_name LIKE ?)";
            $searchTerm = "%" . $search . "%";
            $params = array_fill(0, 5, $searchTerm);
        }

        $filterMapping = [
            'id' => 'books.book_id',
            'publisher_id' => 'publisher.publisher_id',
            'author_id' => 'author.author_id',
            'title' => 'books.books_title',
            'publication_year' => 'books.books_publication_year',
            'isbn' => 'books.books_isbn',
            'stock_quantitiy' => 'book.books_stock_quantity',
            'price' => 'books.books_price',
            'barcode' => 'books.books_barcode',
            'publisher_name' => 'publisher.publisher_name',
            'publisher_address' => 'publisher.publisher_address',
            'publisher_phone' => 'publisher.publisher_phone',
            'publisher_email' => 'publisher.publisher_email',
            'author_name' => 'author.author_name',
            'author_biography' => 'author.author_biography',
        ];

        foreach ($filters as $key => $value) {
            if (!empty($value) && array_key_exists($key, $filterMapping)) {
                $conditions[] = "{$filterMapping[$key]} = ?";
                $params[] = $value;
            }
        }

        if (count($conditions) > 0) {
            $query .= ' WHERE ' . implode(' AND ', $conditions);
        }


        if (!empty($sort)) {
            $sortField = ltrim($sort, '-');
            $sortDirection = $sort[0] === '-' ? 'DESC' : 'ASC';
            if (array_key_exists($sortField, $filterMapping)) {
                $query .= " ORDER BY {$filterMapping[$sortField]} $sortDirection";
            }
        }

        if ($enablePagination) {
            $query .= " LIMIT ? OFFSET ?";
            $params[] = (int) $limit;
            $params[] = (int) $offset;
        }

        try {
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

            $pagination = new \stdClass();
            if ($enablePagination) {
                $totalQuery = "SELECT COUNT(*) as total FROM books
                       JOIN publisher ON books.books_publisher_id = publisher.publisher_id
                       JOIN author ON books.books_author_id = author.author_id";
                if (count($conditions) > 0) {
                    $totalQuery .= ' WHERE ' . implode(' AND ', $conditions);
                }
                $total = $db->query($totalQuery, array_slice($params, 0, count($params) - 2))->getRow()->total;

                $jumlah_page = ceil($total / $limit);
                $pagination = [
                    'total_data' => (int) $total,
                    'jumlah_page' => (int) $jumlah_page,
                    'prev' => ($page > 1) ? $page - 1 : null,
                    'page' => (int) $page,
                    'next' => ($page < $jumlah_page) ? $page + 1 : null,
                    'start' => ($page - 1) * $limit + 1,
                    'end' => min($page * $limit, $total),
                    'detail' => range(max(1, $page - 2), min($jumlah_page, $page + 2)),
                ];
            }

            return $this->respondWithSuccess('Berhasil mendapatkan data buku.', [
                'data' => $result,
                'pagination' => $pagination
            ]);

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

            $data = [

            ];

            if (!$book) {
                return $this->respondWithSuccess('Data tidak tersedia.', $data);
            }


            $result = [
                'data' => [
                    'id' => (int) $book['book_id'],
                    'publisher_id' => (int) $book['books_publisher_id'],
                    'author' => $book['books_author_id'],
                    'title' => $book['books_title'],
                    'publication_year' => $book['books_publication_year'],
                    'stock_quantity' => (int) $book['books_stock_quantity'],
                    'author_id' => (int) $book['books_stock_quantity'],
                    'price' => (int) $book['books_price'],
                    'barcode' => (int) $book['books_barcode'],
                    'isbn' => $book['books_isbn']
                ]
            ];

            return $this->respondWithSuccess('buku di temukan.', $result);
        } catch (DatabaseException $e) {
            return $this->respondWithError('Failed to retrieve book: ' . $e->getMessage());
        }
    }

    // Fungsi untuk memperbarui data buku (Update)
    public function update_book()
    {
        $db = \Config\Database::connect();

        $tokenValidation = $this->validateToken('superadmin,warehouse'); // Fungsi helper dipanggil

        if ($tokenValidation !== true) {
            return $this->respond($tokenValidation, $tokenValidation['status']);
        }

        // Aturan validasi data yang akan diubah
        $rules = [
            'publisher_id' => [
                'rules' => 'required|integer',
                'errors' => [
                    'required' => 'ID penerbit wajib diisi.',
                    'integer' => 'ID penerbit harus berupa angka.'
                ]
            ],
            'author_id' => [
                'rules' => 'required|integer',
                'errors' => [
                    'required' => 'ID penulis wajib diisi.',
                    'integer' => 'ID penulis harus berupa angka.'
                ]
            ],
            'title' => [
                'rules' => 'required|min_length[1]',
                'errors' => [
                    'required' => 'Judul buku wajib diisi.',
                    'min_length' => 'Judul buku harus minimal 1 karakter.'
                ]
            ],
            'publication_year' => [
                'rules' => 'required|integer',
                'errors' => [
                    'required' => 'Tahun terbit wajib diisi.',
                    'integer' => 'Tahun terbit harus berupa angka.'
                ]
            ],
            'isbn' => [
                'rules' => 'required',
                'errors' => [
                    'required' => 'ISBN wajib diisi.',
                ]
            ],
            'stock_quantity' => [
                'rules' => 'required|integer',
                'errors' => [
                    'required' => 'Jumlah stok wajib diisi.',
                    'integer' => 'Jumlah stok harus berupa angka.'
                ]
            ],
            'price' => [
                'rules' => 'required|decimal',
                'errors' => [
                    'required' => 'Harga wajib diisi.',
                    'decimal' => 'Harga harus berupa angka desimal.'
                ]
            ],
            'barcode' => [
                'rules' => 'required|min_length[1]',
                'errors' => [
                    'required' => 'Barcode wajib diisi.',
                    'min_length' => 'Barcode harus minimal 1 karakter.'
                ]
            ]
        ];

        if (!$this->validate($rules)) {
            return $this->respondWithValidationError('Kesalahan validasi', $this->validator->getErrors());
        }
        ;

        $id = $this->request->getVar('id');

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

            $query = "SELECT * FROM books WHERE book_id = ?";
            $book = $db->query($query, [$id])->getRowArray();


            $data = [
                'data' => [
                    'id' => $book['book_id'],
                    'publisher_id' => $book['books_publisher_id'],
                    'author_id' => $book['books_author_id'],
                    'title' => $book['books_title'],
                    'publication_year' => $book['books_publication_year'],
                    'isbn' => $book['books_isbn'],
                    'stock_quantity' => $book['books_stock_quantity'],
                    'price' => $book['books_price'],
                    'barcode' => $book['books_barcode']
                ]
            ];

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


    public function stock()
    {
        $db = \Config\Database::connect();
        $id = $this->request->getVar('id');
        $additional_stock = $this->request->getVar('stock');
        $type = $this->request->getVar('type');

        // Validasi token (hanya warehouse yang bisa update stock)
        $tokenValidation = $this->validateToken('warehouse,superadmin');
        if ($tokenValidation !== true) {
            return $this->respond($tokenValidation, $tokenValidation['status']);
        }

        // Validasi input untuk memastikan 'id', 'stock', dan 'type' valid
        $validation = \Config\Services::validation();
        $validation->setRules([
            'id' => [
                'label' => 'ID Buku',
                'rules' => 'required|integer',
                'errors' => [
                    'required' => 'ID buku wajib diisi.',
                    'integer' => 'ID buku harus berupa angka.'
                ]
            ],
            'stock' => [
                'label' => 'Stock',
                'rules' => 'required|integer|greater_than_equal_to[0]',
                'errors' => [
                    'required' => 'Stock wajib diisi.',
                    'integer' => 'Stock harus berupa angka.',
                    'greater_than_equal_to' => 'Stock harus lebih besar atau sama dengan 0.'
                ]
            ],
            'type' => [
                'label' => 'Type',
                'rules' => 'required|in_list[masuk,keluar]',
                'errors' => [
                    'required' => 'Type wajib diisi.',
                    'in_list' => 'Type harus salah satu dari: masuk, keluar.'
                ]
            ]
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->respond([
                'status' => 412,
                'message' => 'Validasi error',
                'errors' => $validation->getErrors()
            ], 412);
        }

        try {
            // Cek apakah buku dengan ID tersebut ada
            $query = "SELECT * FROM books WHERE book_id = ?";
            $book = $db->query($query, [$id])->getRow();

            if (!$book) {
                return $this->respondWithError('Failed to update stock: Book not found.', null, 404);
            }

            // Debugging: Log the initial stock and additional stock
            log_message('debug', 'Initial stock: ' . $book->books_stock_quantity);
            log_message('debug', 'Additional stock: ' . $additional_stock);

            // Hitung stock baru berdasarkan type
            if ($type === 'masuk') {
                // Untuk 'masuk', tambahkan stock
                $new_stock = (int) $book->books_stock_quantity + (int) $additional_stock;
            } else {
                // Untuk 'keluar', kurangi stock
                $new_stock = (int) $book->books_stock_quantity - (int) $additional_stock;
                // Pastikan stock tidak menjadi negatif
                if ($new_stock < 0) {
                    return $this->respondWithError('Stock tidak bisa kurang dari 0.', null, 400);
                }
            }

            // Lakukan update stock
            $query = "UPDATE books SET books_stock_quantity = ? WHERE book_id = ?";
            $db->query($query, [$new_stock, $id]);

            // Kembalikan respons lengkap
            return $this->respond([
                'status' => 200,
                'message' => 'Stock updated successfully.',
                'result' => [
                    'data' => [
                        'id' => $book->book_id,
                        'title' => $book->books_title,
                        'author_id' => $book->books_author_id,
                        'publisher_id' => $book->books_publisher_id,
                        'publication_year' => $book->books_publication_year,
                        'isbn' => $book->books_isbn,
                        'stock_quantity' => $new_stock,
                        'price' => $book->books_price,
                        'barcode' => $book->books_barcode
                    ]
                ]
            ], 200);
        } catch (\Exception $e) {
            return $this->respondWithError('Failed to update stock: ' . $e->getMessage());
        }
    }


}
