<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;

class AuthorController extends ResourceController
{
    protected $format = 'json';

    public function index()
    {
        $db = \Config\Database::connect();
        $query = $db->query("SELECT * FROM author");
        $authors = $query->getResult();

        return $this->respond($authors);
    }

    public function show($author_id = null)
    {
        if ($author_id === null) {
            return $this->fail('Author ID is required', 400);
        }

        $db = \Config\Database::connect();
        $query = $db->query("SELECT * FROM author WHERE author_id = ?", [$author_id]);
        $author = $query->getRow();

        if (!$author) {
            return $this->failNotFound('Author not found');
        }

        return $this->respond($author);
    }

    public function create()
    {
        $rules = [
            'author_name' => 'required|min_length[5]|max_length[100]',
            'biography'   => 'required|min_length[10]|max_length[1000]',
        ];

        if (!$this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $db = \Config\Database::connect();
        $query = "INSERT INTO author (author_name, biography) VALUES (:author_name:, :biography:)";
        $params = [
            'author_name' => $this->request->getVar('author_name'),
            'biography'   => $this->request->getVar('biography'),
        ];
        $db->query($query, $params);

        $response = [
            'status' => 201,
            'message' => 'Author added',
        ];

        return $this->respondCreated($response);
    }

    public function update($author_id = null)
    {
        if ($author_id === null) {
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
        $query = "UPDATE author SET author_name = :author_name:, biography = :biography: WHERE author_id = :author_id:";
        $params = [
            'author_name' => $this->request->getVar('author_name'),
            'biography'   => $this->request->getVar('biography'),
            'author_id'   => $author_id,
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

    public function delete($author_id = null)
    {
        if ($author_id === null) {
            return $this->fail('Author ID is required', 400);
        }

        $db = \Config\Database::connect();
        $query = "DELETE FROM author WHERE author_id = :author_id:";
        $params = ['author_id' => $author_id];
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
