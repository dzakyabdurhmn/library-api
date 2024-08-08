<?php

namespace App\Controllers;

class PublisherController extends CoreController
{
    protected $format = 'json';

    public function index()
    {
        $db = \Config\Database::connect();
        $builder = $db->table('publisher');

        // Filter
        if ($this->request->getGet('name')) {
            $builder->like('publisher_name', $this->request->getGet('name'));
        }

        // Search
        if ($this->request->getGet('search')) {
            $builder->like('publisher_name', $this->request->getGet('search'));
        }

        // Pagination
        $limit = $this->request->getGet('limit') ?? 10;
        $page = $this->request->getGet('page') ?? 1;
        $offset = ($page - 1) * $limit;
        $builder->limit($limit, $offset);

        $publishers = $builder->get()->getResult();

        return $this->respondWithSuccess('Publishers retrieved successfully', $publishers, 200);
    }
    
    public function show($publisher_id = null)
    {
        if ($publisher_id === null) {
            return $this->respondWithValidationError('Publisher ID is required', [], 400);
        }

        $db = \Config\Database::connect();
        $query = $db->query("SELECT * FROM publisher WHERE publisher_id = ?", [$publisher_id]);
        $publisher = $query->getRow();

        if (!$publisher) {
            return $this->respondWithNotFound('Publisher not found');
        }

        return $this->respondWithSuccess('Publisher retrieved successfully', $publisher, 200);
    }

    public function create()
    {
        $rules = [
            'publisher_name' => 'required|min_length[5]|max_length[100]',
            'address'        => 'required|min_length[10]|max_length[255]',
            'phone'          => 'required|min_length[10]|max_length[15]',
            'email'          => 'required|valid_email|min_length[5]|max_length[100]',
        ];

        if (!$this->validate($rules)) {
            return $this->respondWithValidationError('Validation errors', $this->validator->getErrors());
        }

        $db = \Config\Database::connect();
        $query = "INSERT INTO publisher (publisher_name, address, phone, email) VALUES (:publisher_name:, :address:, :phone:, :email:)";
        $params = [
            'publisher_name' => $this->request->getVar('publisher_name'),
            'address'        => $this->request->getVar('address'),
            'phone'          => $this->request->getVar('phone'),
            'email'          => $this->request->getVar('email'),
        ];
        $db->query($query, $params);

        return $this->respondWithSuccess('Publisher created successfully', null, 201);
    }

    public function update($publisher_id = null)
    {
        if ($publisher_id === null) {
            return $this->respondWithValidationError('Publisher ID is required', [], 400);
        }

        $rules = [
            'publisher_name' => 'required|min_length[5]|max_length[100]',
            'address'        => 'required|min_length[10]|max_length[255]',
            'phone'          => 'required|min_length[10]|max_length[15]',
            'email'          => 'required|valid_email|min_length[5]|max_length[100]',
        ];

        if (!$this->validate($rules)) {
            return $this->respondWithValidationError('Validation errors', $this->validator->getErrors());
        }

        $db = \Config\Database::connect();
        $query = "UPDATE publisher SET publisher_name = :publisher_name:, address = :address:, phone = :phone:, email = :email: WHERE publisher_id = :publisher_id:";
        $params = [
            'publisher_name' => $this->request->getVar('publisher_name'),
            'address'        => $this->request->getVar('address'),
            'phone'          => $this->request->getVar('phone'),
            'email'          => $this->request->getVar('email'),
            'publisher_id'   => $publisher_id,
        ];
        $db->query($query, $params);

        if ($db->affectedRows() == 0) {
            return $this->respondWithNotFound('Publisher not found or no changes made');
        }

        return $this->respondWithSuccess('Publisher updated successfully', null, 200);
    }

    public function delete($publisher_id = null)
    {
        if ($publisher_id === null) {
            return $this->respondWithValidationError('Publisher ID is required', [], 400);
        }

        $db = \Config\Database::connect();
        $query = "DELETE FROM publisher WHERE publisher_id = :publisher_id:";
        $params = ['publisher_id' => $publisher_id];
        $db->query($query, $params);

        if ($db->affectedRows() == 0) {
            return $this->respondWithNotFound('Publisher not found');
        }

        return $this->respondWithDeleted('Publisher deleted successfully', 200);
    }
}
