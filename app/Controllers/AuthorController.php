<?php

namespace App\Controllers;

class AuthorController extends CoreController
{
    protected $format = 'json';

    public function index()
    {
        $db = \Config\Database::connect();
        $builder = $db->table('author');

        // Filter
        if ($this->request->getGet('name')) {
            $builder->like('author_name', $this->request->getGet('name'));
        }

        // Search
        if ($this->request->getGet('search')) {
            $builder->like('author_name', $this->request->getGet('search'));
        }

        // Pagination
        $limit = $this->request->getGet('limit') ?? 10;
        $page = $this->request->getGet('page') ?? 1;
        $offset = ($page - 1) * $limit;
        $builder->limit($limit, $offset);

        $authors = $builder->get()->getResult();

        return $this->respondWithSuccess('Authors retrieved successfully', $authors, 200);
    }
    public function show($author_id = null)
    {
        if ($author_id === null) {
            return $this->respondWithValidationError('Author ID is required', [], 400);
        }

        $db = \Config\Database::connect();
        $query = $db->query("SELECT * FROM author WHERE author_id = ?", [$author_id]);
        $author = $query->getRow();

        if (!$author) {
            return $this->respondWithNotFound('Author not found');
        }

        return $this->respondWithSuccess('Author retrieved successfully', $author, 200);
    }

    public function create()
    {
        $rules = [
            'author_name' => 'required|min_length[5]|max_length[100]',
            'biography'   => 'required|min_length[10]|max_length[1000]',
        ];

        if (!$this->validate($rules)) {
            return $this->respondWithValidationError('Validation errors', $this->validator->getErrors());
        }

        $db = \Config\Database::connect();
        $query = "INSERT INTO author (author_name, biography) VALUES (:author_name:, :biography:)";
        $params = [
            'author_name' => $this->request->getVar('author_name'),
            'biography'   => $this->request->getVar('biography'),
        ];
        $db->query($query, $params);

        return $this->respondWithSuccess('Author added', null, 201);
    }

    public function update($author_id = null)
    {
        if ($author_id === null) {
            return $this->respondWithValidationError('Author ID is required', [], 400);
        }

        $rules = [
            'author_name' => 'required|min_length[5]|max_length[100]',
            'biography'   => 'required|min_length[10]|max_length[1000]',
        ];

        if (!$this->validate($rules)) {
            return $this->respondWithValidationError('Validation errors', $this->validator->getErrors());
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
            return $this->respondWithNotFound('Author not found or no changes made');
        }

        return $this->respondWithSuccess('Author updated', null, 200);
    }

    public function delete($author_id = null)
    {
        if ($author_id === null) {
            return $this->respondWithValidationError('Author ID is required', [], 400);
        }

        $db = \Config\Database::connect();
        $query = "DELETE FROM author WHERE author_id = :author_id:";
        $params = ['author_id' => $author_id];
        $db->query($query, $params);

        if ($db->affectedRows() == 0) {
            return $this->respondWithNotFound('Author not found');
        }

        return $this->respondWithDeleted('Author deleted', 200);
    }
}
