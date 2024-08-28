<?php

namespace App\Controllers;

class BookController extends CoreController
{
    protected $format = 'json';

    public function index()
    {
        $db = \Config\Database::connect();
        $builder = $db->table('catalog_books');

        // Filter
        if ($this->request->getGet('title')) {
            $builder->like('title', $this->request->getGet('title'));
        }

        if ($this->request->getGet('author')) {
            $builder->like('author_id', $this->request->getGet('author'));
        }

        // Search
        if ($this->request->getGet('search')) {
            $builder->like('title', $this->request->getGet('search'))
                    ->orLike('author_id', $this->request->getGet('search'));
        }

        // Pagination
        $limit = $this->request->getGet('limit') ?? 10;
        $page = $this->request->getGet('page') ?? 1;
        $offset = ($page - 1) * $limit;
        $builder->limit($limit, $offset);

        $catalog_books = $builder->get()->getResult();

        return $this->respondWithSuccess('Books retrieved successfully', $catalog_books, 200);
    }

    public function available_book()
    {
        $db = \Config\Database::connect();
        $builder = $db->table('catalog_books')->where('stock_quantity >', 0);

        // Filter
        if ($this->request->getGet('title')) {
            $builder->like('title', $this->request->getGet('title'));
        }

        if ($this->request->getGet('author')) {
            $builder->like('author_id', $this->request->getGet('author'));
        }

        // Search
        if ($this->request->getGet('search')) {
            $builder->like('title', $this->request->getGet('search'))
                    ->orLike('author_id', $this->request->getGet('search'));
        }

        // Pagination
        $limit = $this->request->getGet('limit') ?? 10;
        $page = $this->request->getGet('page') ?? 1;
        $offset = ($page - 1) * $limit;
        $builder->limit($limit, $offset);

        $catalog_books = $builder->get()->getResult();

        return $this->respondWithSuccess('Available books retrieved successfully', $catalog_books, 200);
    }

    public function show($book_id = null)
    {
        if ($book_id === null) {
            return $this->respondWithValidationError('Book ID is required', [], 400);
        }

        $db = \Config\Database::connect();
        $query = $db->query("SELECT * FROM catalog_books WHERE book_id = ?", [$book_id]);
        $book = $query->getRow();

        if (!$book) {
            return $this->respondWithNotFound('Book not found');
        }

        return $this->respondWithSuccess('Book retrieved successfully', $book, 200);
    }

// public function create()
// {
//     $rules = [
//         'title' => 'required|min_length[5]|max_length[100]',
//         'publisher_id' => 'required|min_length[1]|max_length[1000]',
//         'publication_year' => 'required|min_length[4]|max_length[1000]',
//         'isbn' => 'required|min_length[5]|max_length[1000]',
//         'author_id' => 'required|min_length[1]|max_length[1000]',
//         'book_price' => 'required|numeric|min_length[1]|max_length[1000]',
//     ];

//     if (!$this->validate($rules)) {
//         return $this->respondWithValidationError('Validation errors', $this->validator->getErrors());
//     }

//     $book_price = $this->request->getVar('book_price');
//     $loan_price = $book_price * 0.01;

//     $db = \Config\Database::connect();
//     $query = "INSERT INTO catalog_books (title, publisher_id, publication_year, isbn, author_id, book_price, loan_price) VALUES (:title:, :publisher_id:, :publication_year:, :isbn:, :author_id:, :book_price:, :loan_price:)";
//     $params = [
//         'title' => $this->request->getVar('title'),
//         'publisher_id' => $this->request->getVar('publisher_id'),
//         'publication_year' => $this->request->getVar('publication_year'),
//         'isbn' => $this->request->getVar('isbn'),
//         'author_id' => $this->request->getVar('author_id'),
//         'book_price' => $book_price,
//         'loan_price' => $loan_price,
//     ];
//     $db->query($query, $params);

//     return $this->respondWithSuccess('Book added successfully', null, 201);
// }


public function create()
{
    $rules = [
        'title' => 'required|min_length[5]|max_length[100]',
        'publisher_id' => 'required|min_length[1]|max_length[1000]',
        'publication_year' => 'required|min_length[4]|max_length[1000]',
        'isbn' => 'required|min_length[5]|max_length[1000]',
        'author_id' => 'required|min_length[1]|max_length[1000]',
        'book_price' => 'required|numeric|min_length[1]|max_length[1000]',
    ];

    if (!$this->validate($rules)) {
        return $this->respondWithValidationError('Validation errors', $this->validator->getErrors());
    }

    // Mengambil nilai persentase yang berlaku dari tabel 'percentage'
    $db = \Config\Database::connect();
    $percentageQuery = $db->query("SELECT percentage FROM percentage WHERE effective_date <= CURRENT_DATE ORDER BY effective_date DESC LIMIT 1");
    $percentageResult = $percentageQuery->getRow();

    if (!$percentageResult) {
        return $this->respondWithError('No valid percentage found in the database');
    }

    $percentage = $percentageResult->percentage;
    
    $book_price = $this->request->getVar('book_price');
    $loan_price = $book_price * ($percentage / 100);  // Menggunakan persentase dari database

    // Memasukkan data buku ke dalam tabel 'catalog_books'
    $query = "INSERT INTO catalog_books (title, publisher_id, publication_year, isbn, author_id, book_price, loan_price) VALUES (:title:, :publisher_id:, :publication_year:, :isbn:, :author_id:, :book_price:, :loan_price:)";
    $params = [
        'title' => $this->request->getVar('title'),
        'publisher_id' => $this->request->getVar('publisher_id'),
        'publication_year' => $this->request->getVar('publication_year'),
        'isbn' => $this->request->getVar('isbn'),
        'author_id' => $this->request->getVar('author_id'),
        'book_price' => $book_price,
        'loan_price' => $loan_price,
    ];
    $db->query($query, $params);

    return $this->respondWithSuccess('Book added successfully', null, 201);
}



    public function update($book_id = null)
    {
        if ($book_id === null) {
            return $this->respondWithValidationError('Book ID is required', [], 400);
        }

        $rules = [
            'title' => 'required|min_length[5]|max_length[100]',
            'publisher_id' => 'required|min_length[1]|max_length[1000]',
            'publication_year' => 'required|min_length[4]|max_length[1000]',
            'isbn' => 'required|min_length[5]|max_length[1000]',
            'author_id' => 'required|min_length[1]|max_length[1000]',
        ];

        if (!$this->validate($rules)) {
            return $this->respondWithValidationError('Validation errors', $this->validator->getErrors());
        }

        $db = \Config\Database::connect();
        $query = "UPDATE catalog_books SET title = :title:, publisher_id = :publisher_id:, publication_year = :publication_year:, isbn = :isbn:, author_id = :author_id: WHERE book_id = :book_id:";
        $params = [
            'title' => $this->request->getVar('title'),
            'publisher_id' => $this->request->getVar('publisher_id'),
            'publication_year' => $this->request->getVar('publication_year'),
            'isbn' => $this->request->getVar('isbn'),
            'author_id' => $this->request->getVar('author_id'),
            'book_id' => $book_id,
        ];
        $db->query($query, $params);

        if ($db->affectedRows() == 0) {
            return $this->respondWithNotFound('Book not found or no changes made');
        }

        return $this->respondWithSuccess('Book updated successfully', null, 200);
    }

    public function delete($book_id = null)
    {
        if ($book_id === null) {
            return $this->respondWithValidationError('Book ID is required', [], 400);
        }

        $db = \Config\Database::connect();
        $query = "DELETE FROM catalog_books WHERE book_id = :book_id:";
        $params = ['book_id' => $book_id];
        $db->query($query, $params);

        if ($db->affectedRows() == 0) {
            return $this->respondWithNotFound('Book not found');
        }

        return $this->respondWithDeleted('Book deleted successfully', 200);
    }

    public function update_stock($book_id = null)
    {
        if ($book_id === null) {
            return $this->respondWithValidationError('Book ID is required', [], 400);
        }

        $rules = [
            'stock_quantity' => 'required|min_length[1]|max_length[100]',
        ];

        if (!$this->validate($rules)) {
            return $this->respondWithValidationError('Validation errors', $this->validator->getErrors());
        }

        $db = \Config\Database::connect();
        $query = "UPDATE catalog_books SET stock_quantity = :stock_quantity: WHERE book_id = :book_id:";
        $params = [
            'stock_quantity' => $this->request->getVar('stock_quantity'),
            'book_id' => $book_id,
        ];
        $db->query($query, $params);

        if ($db->affectedRows() == 0) {
            return $this->respondWithNotFound('Book not found or no changes made');
        }

        return $this->respondWithSuccess('Stock updated successfully', null, 200);
    }
}
