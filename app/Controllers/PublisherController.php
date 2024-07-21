<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;

class PublisherController extends ResourceController
{
    protected $format = 'json';

    public function index()
    {
        $db = \Config\Database::connect();
        $query = $db->query("SELECT * FROM publisher");
        $publishers = $query->getResult();

        return $this->respond($publishers);
    }

    public function show($publisher_id = null)
    {
        if ($publisher_id === null) {
            return $this->fail('Publisher ID is required', 400);
        }

        $db = \Config\Database::connect();
        $query = $db->query("SELECT * FROM publisher WHERE publisher_id = ?", [$publisher_id]);
        $publisher = $query->getRow();

        if (!$publisher) {
            return $this->failNotFound('Publisher not found');
        }

        return $this->respond($publisher);
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
            return $this->failValidationErrors($this->validator->getErrors());
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

        $response = [
            'status' => 201,
            'message' => 'Publisher created successfully',
        ];

        return $this->respondCreated($response);
    }

    public function update($publisher_id = null)
    {
        if ($publisher_id === null) {
            return $this->fail('Publisher ID is required', 400);
        }

        $rules = [
            'publisher_name' => 'required|min_length[5]|max_length[100]',
            'address'        => 'required|min_length[10]|max_length[255]',
            'phone'          => 'required|min_length[10]|max_length[15]',
            'email'          => 'required|valid_email|min_length[5]|max_length[100]',
        ];

        if (!$this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
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
            return $this->failNotFound('Publisher not found or no changes made');
        }

        $response = [
            'status' => 200,
            'message' => 'Publisher updated successfully',
        ];

        return $this->respond($response);
    }

    public function delete($publisher_id = null)
    {
        if ($publisher_id === null) {
            return $this->fail('Publisher ID is required', 400);
        }

        $db = \Config\Database::connect();
        $query = "DELETE FROM publisher WHERE publisher_id = :publisher_id:";
        $params = ['publisher_id' => $publisher_id];
        $db->query($query, $params);

        if ($db->affectedRows() == 0) {
            return $this->failNotFound('Publisher not found');
        }

        $response = [
            'status' => 200,
            'message' => 'Publisher deleted successfully',
        ];

        return $this->respondDeleted($response);
    }
}
