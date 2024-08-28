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

public function update($id = null)
{
    if ($id === null) {
        return $this->respondWithError('No Member ID provided', null, 400);
    }

    $db = \Config\Database::connect();
    $builder = $db->table('member');

    // Check if member exists
    $member = $builder->getWhere(['user_id' => $id])->getRow();
    if (!$member) {
        return $this->respondWithError('Member not found', null, 404);
    }

    $rules = [
        'username' => "permit_empty|min_length[3]|max_length[100]|is_unique[member.username,user_id,{$id}]",
        'email'    => "permit_empty|valid_email|is_unique[member.email,user_id,{$id}]",
        'full_name' => 'permit_empty|min_length[8]',
        'address' => 'permit_empty|min_length[8]',
    ];

    if (!$this->validate($rules)) {
        return $this->respondWithValidationError('Validation errors', $this->validator->getErrors());
    }

    $data = [];
    if ($this->request->getVar('username')) {
        $data['username'] = $this->request->getVar('username');
    }
    if ($this->request->getVar('email')) {
        $data['email'] = $this->request->getVar('email');
    }
    if ($this->request->getVar('full_name')) {
        $data['full_name'] = $this->request->getVar('full_name');
    }
    if ($this->request->getVar('address')) {
        $data['address'] = $this->request->getVar('address');
    }

    if (!empty($data)) {
        $builder->where('user_id', $id);
        $builder->update($data);

        // Dapatkan kembali data member yang telah diperbarui
        $updatedMember = $builder->getWhere(['user_id' => $id])->getRow();

        return $this->respondWithSuccess('Member updated successfully', $updatedMember, 200);
    }

    return $this->respondWithError('No data to update', null, 400);
}


 public function delete($id = null)
    {
        if ($id === null) {
            return $this->fail('No ID provided');
        }

        $db = \Config\Database::connect();
        $memberModel = $db->table('loan_user');
        
        try {
            $memberModel->where('user_id', $id)->delete();
            return $this->respond(['message' => 'Member deleted successfully'], 200);
        } catch (DatabaseException $e) {
            if ($this->isForeignKeyConstraintError($e)) {
                return $this->fail('User is currently borrowing books. Please return the books before deleting the member.');
            }
            return $this->fail('An error occurred: ' . $e->getMessage());
        }
    }

    private function isForeignKeyConstraintError(DatabaseException $e)
    {
        // Check if the exception message contains "foreign key constraint fails"
        return strpos($e->getMessage(), 'foreign key constraint fails') !== false;
    }

}
