<?php

namespace App\Controllers;

class MemberController extends CoreController
{
    protected $modelName = 'App\Models\UserModel';
    protected $format = 'json';

     public function index()
    {
        $db = \Config\Database::connect();
        $builder = $db->table('member');

        // Filter
        if ($this->request->getGet('name')) {
            $builder->like('full_name', $this->request->getGet('name'));
        }

        // Search
        if ($this->request->getGet('search')) {
            $builder->like('full_name', $this->request->getGet('search'));
        }

        // Pagination
        $limit = $this->request->getGet('limit') ?? 10;
        $page = $this->request->getGet('page') ?? 1;
        $offset = ($page - 1) * $limit;
        $builder->limit($limit, $offset);

        $members = $builder->get()->getResult();

        return $this->respondWithSuccess('Members retrieved successfully', $members, 200);
    }

    public function create()
    {
        $rules = [
            'username' => 'required|min_length[3]|max_length[100]|is_unique[member.username],"The %s is already taken"',
            'email'    => 'required|valid_email|is_unique[member.email]',
            'full_name' => 'required|min_length[8]',
            'address' => 'required|min_length[8]',
        ];

        if (!$this->validate($rules)) {
            return $this->respondWithValidationError('Validation errors', $this->validator->getErrors());
        }

        $db = \Config\Database::connect();
        $query = "INSERT INTO member (username, email, full_name, address) VALUES (:username:, :email:, :full_name:, :address:)";
        $params = [
            'username' => $this->request->getVar('username'),
            'email' => $this->request->getVar('email'),
            'full_name' => $this->request->getVar('full_name'),
            'address' => $this->request->getVar('address'),
        ];

        $db->query($query, $params);

        return $this->respondWithSuccess('Member registered successfully', null, 201);
    }
}
