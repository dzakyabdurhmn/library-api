<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;

class BookController extends ResourceController
{
    protected $format = 'json';

    public function index()
    {
        $db = \Config\Database::connect();
        $query = $db->query("SELECT * FROM catalog_books");
        $catalog_books = $query->getResult();

        return $this->respond($catalog_books);
    }

    public function show($book_id = null)
    {
        if ($book_id === null) {
            return $this->fail('Author ID is required', 400);
        }

        $db = \Config\Database::connect();
        $query = $db->query("SELECT * FROM catalog_books WHERE book_id = ?", [$book_id]);
        $author = $query->getRow();

        if (!$author) {
            return $this->failNotFound('Book not found');
        }

        return $this->respond($author);
    }

    public function create()
    {
        $rules = [
            'title' => 'required|min_length[5]|max_length[100]',
            'publisher_id'   => 'required|min_length[1]|max_length[1000]',
            'publication_year'   => 'required|min_length[4]|max_length[1000]',
            'isbn'   => 'required|min_length[10]|max_length[1000]',
            'author_id'   => 'required|min_length[1]|max_length[1000]',
            // ADD LOGIC STOCK
        ];

        if (!$this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $db = \Config\Database::connect();
        $query = "INSERT INTO catalog_books (title, publisher_id, publication_year, isbn, author_id) VALUES (:title:, :publisher_id:, :publication_year:, :isbn:, :author_id:)";
        $params = [
            'title' => $this->request->getVar('title'),
            'publisher_id'   => $this->request->getVar('publisher_id'),
            'publication_year'   => $this->request->getVar('publication_year'),
            'isbn'   => $this->request->getVar('isbn'),
            'author_id'   => $this->request->getVar('author_id'),
        ];
        $db->query($query, $params);

        $response = [
            'status' => 201,
            'message' => 'Book added if you have stock lebih dari one add in stock',
        ];

        return $this->respondCreated($response);
    }

    public function update($book_id = null)
    {
        if ($book_id === null) {
            return $this->fail('Author ID is required', 400);
        }

        $rules = [
            'author_name' => 'required|min_length[5]|max_length[100]',
            'biography'   => 'required|min_length[10]|max_length[1000]',
        ];

        if (!$this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $db = \Config\Database::connect();
        $query = "UPDATE author SET author_name = :author_name:, biography = :biography: WHERE book_id = :book_id:";
        $params = [
            'author_name' => $this->request->getVar('author_name'),
            'biography'   => $this->request->getVar('biography'),
            'book_id'   => $book_id,
        ];
        $db->query($query, $params);

        if ($db->affectedRows() == 0) {
            return $this->failNotFound('Author not found or no changes made');
        }

        $response = [
            'status' => 200,
            'message' => 'Author updated',
        ];

        return $this->respond($response);
    }

    public function delete($book_id = null)
    {
        if ($book_id === null) {
            return $this->fail('Author ID is required', 400);
        }

        $db = \Config\Database::connect();
        $query = "DELETE FROM catalog_books WHERE book_id = :book_id:";
        $params = ['book_id' => $book_id];
        $db->query($query, $params);

        if ($db->affectedRows() == 0) {
            return $this->failNotFound('Author not found');
        }

        $response = [
            'status' => 200,
            'message' => 'Author deleted',
        ];

        return $this->respondDeleted($response);
    }
}
