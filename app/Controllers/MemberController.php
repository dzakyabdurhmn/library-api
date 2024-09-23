<?php

namespace App\Controllers;

use CodeIgniter\Database\Exceptions\DatabaseException;

class MemberController extends CoreController
{
    // Fungsi untuk menambahkan member baru (Create)
    public function create()
    {
        $db = \Config\Database::connect();

        $rules = [
            'username' => 'required|min_length[5]',
            'email' => 'required|valid_email',
            'full_name' => 'required',
            'address' => 'required',
            'job' => 'required',
            'status' => 'required',
            'religion' => 'required',
            'barcode' => 'required',
            'gender' => 'required|in_list[MEN,WOMEN]'
        ];

        if (!$this->validate($rules)) {
            return $this->respondWithValidationError('Validation errors', $this->validator->getErrors());
        }

        $data = [
            'member_username' => $this->request->getVar('username'),
            'member_email' => $this->request->getVar('email'),
            'member_full_name' => $this->request->getVar('full_name'),
            'member_address' => $this->request->getVar('address'),
            'member_job' => $this->request->getVar('job'),
            'member_status' => $this->request->getVar('status'),
            'member_religion' => $this->request->getVar('religion'),
            'member_barcode' => $this->request->getVar('barcode'),
            'member_gender' => $this->request->getVar('gender'),
        ];

        try {
            // Raw query untuk insert data member
            $query = "INSERT INTO member (member_username, member_email, member_full_name, member_address, member_job, member_status, member_religion, member_barcode, member_gender) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $db->query($query, [
                $data['member_username'],
                $data['member_email'],
                $data['member_full_name'],
                $data['member_address'],
                $data['member_job'],
                $data['member_status'],
                $data['member_religion'],
                $data['member_barcode'],
                $data['member_gender']
            ]);

            return $this->respondWithSuccess('Member added successfully.');
        } catch (DatabaseException $e) {
            return $this->respondWithError('Failed to add member: ' . $e->getMessage());
        }
    }

    // Fungsi untuk mendapatkan detail member berdasarkan member_id (Read)
    public function show($id = null)
    {
        $db = \Config\Database::connect();

        try {
            // Raw query untuk mendapatkan data member berdasarkan member_id
            $query = "SELECT * FROM member WHERE member_id = ?";
            $member = $db->query($query, [$id])->getRowArray();




            if (!$member) {
                return $this->respondWithNotFound('Member not found.');
            }

            $response = [
                'id' => $member['member_id'],
                'username' => $member['member_username'],
                'email' => $member['member_email'],
                'full_name' => $member['member_full_name'],
                'address' => $member['member_address'],
                'job' => $member['member_job'],
                'status' => $member['member_status'],
                'religion' => $member['member_religion'],
                'barcode' => $member['member_barcode'],
                'gender' => $member['member_gender']
            ];


            return $this->respondWithSuccess('Member found.', $response);
        } catch (DatabaseException $e) {
            return $this->respondWithError('Failed to retrieve member: ' . $e->getMessage());
        }
    }

    // Fungsi untuk memperbarui data member (Update)

    public function update($id = null)
    {
        $db = \Config\Database::connect();

        // Define validation rules for all fields
        $validationRules = [
            'username' => 'min_length[5]',
            'email' => 'valid_email',
            'full_name' => 'min_length[3]',
            'address' => 'min_length[3]',
            'job' => 'min_length[3]',
            'status' => 'in_list[active,inactive]', // Example values, adjust as needed
            'religion' => 'min_length[3]',
            'barcode' => 'min_length[3]',
            'gender' => 'in_list[MEN,WOMEN]',
        ];

        // Prepare an array of possible fields and the corresponding database columns
        $fields = [
            'username' => 'member_username',
            'email' => 'member_email',
            'full_name' => 'member_full_name',
            'address' => 'member_address',
            'job' => 'member_job',
            'status' => 'member_status',
            'religion' => 'member_religion',
            'barcode' => 'member_barcode',
            'gender' => 'member_gender',
        ];

        // Collect the data to update (only fields present in the request)
        $data = [];
        $validationData = [];
        foreach ($fields as $key => $dbField) {
            $value = $this->request->getVar($key);
            if ($value !== null) {
                $data[$dbField] = $value;
                $validationData[$key] = $value; // Prepare the data for validation
            }
        }

        // If no fields are provided, return an error response
        if (empty($data)) {
            return $this->respondWithError('No data provided to update.');
        }

        // Validate only the fields that are present in the request
        if (!$this->validate(array_intersect_key($validationRules, $validationData))) {
            return $this->respondWithValidationError('Validation errors', $this->validator->getErrors());
        }

        try {
            // Dynamically build the SQL query based on the fields provided
            $setClauses = [];
            $params = [];
            foreach ($data as $dbField => $value) {
                $setClauses[] = "$dbField = ?";
                $params[] = $value;
            }
            $params[] = $id; // Add the ID for the WHERE clause

            // Create the update query
            $query = "UPDATE member SET " . implode(', ', $setClauses) . " WHERE member_id = ?";

            // Execute the update query
            $db->query($query, $params);

            // Fetch the updated member information
            $updatedMember = $db->query("SELECT * FROM member WHERE member_id = ?", [$id])->getRowArray();

            // Check if the member exists before accessing the array
            if (!$updatedMember) {
                return $this->respondWithError("Member with ID $id not found.");
            }

            // Prepare the response with updated data
            $response = [
                'id' => $updatedMember['member_id'],
                'username' => $updatedMember['member_username'],
                'email' => $updatedMember['member_email'],
                'full_name' => $updatedMember['member_full_name'],
                'address' => $updatedMember['member_address'],
                'job' => $updatedMember['member_job'],
                'status' => $updatedMember['member_status'],
                'religion' => $updatedMember['member_religion'],
                'barcode' => $updatedMember['member_barcode'],
                'gender' => $updatedMember['member_gender'],
            ];

            // Return success response with updated member data
            return $this->respondWithSuccess('Member updated successfully.', $response);

        } catch (DatabaseException $e) {
            // Return error response if there's a database error
            return $this->respondWithError('Failed to update member: ' . $e->getMessage());
        }
    }



    // Fungsi untuk menghapus member berdasarkan member_id (Delete)
    public function delete($id = null)
    {
        $db = \Config\Database::connect();




        try {
            // Raw query untuk menghapus data member berdasarkan member_id
            $query = "DELETE FROM member WHERE member_id = ?";


            $db->query($query, [$id]);



            return $this->respondWithSuccess('Member deleted successfully.');
        } catch (DatabaseException $e) {
            return $this->respondWithError('Failed to delete member: ' . $e->getMessage());
        }
    }

    // Fungsi untuk mendapatkan semua member (List)


    // Fungsi untuk mendapatkan semua member dengan pagination, search, dan filter
    public function index()
    {
        $db = \Config\Database::connect();

        // Get parameters from query string
        $limit = $this->request->getVar('limit') ?? 10; // Default limit is 10
        $page = $this->request->getVar('page') ?? 1; // Default page is 1
        $search = $this->request->getVar('search');
        $filter = $this->request->getVar('filter'); // Can be status, job, etc.

        // Calculate offset for pagination
        $offset = ($page - 1) * $limit;

        try {
            // Base query
            $query = "SELECT * FROM member";
            $conditions = [];
            $params = [];

            // Add filter and search if present
            if ($search) {
                $conditions[] = "(member_username LIKE ? OR member_full_name LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }

            if ($filter) {
                $conditions[] = "member_status = ?";
                $params[] = $filter;
            }

            if (count($conditions) > 0) {
                $query .= ' WHERE ' . implode(' AND ', $conditions);
            }

            // Add limit and offset for pagination
            $query .= " LIMIT ? OFFSET ?";
            $params[] = (int) $limit;
            $params[] = (int) $offset;

            // Execute the query and fetch results
            $members = $db->query($query, $params)->getResultArray();

            // Prepare the members response
            $response = [];
            foreach ($members as $member) {
                $response[] = [
                    'id' => $member['member_id'],
                    'username' => $member['member_username'],
                    'email' => $member['member_email'],
                    'full_name' => $member['member_full_name'],
                    'address' => $member['member_address'],
                    'job' => $member['member_job'],
                    'status' => $member['member_status'],
                    'religion' => $member['member_religion'],
                    'barcode' => $member['member_barcode'],
                    'gender' => $member['member_gender'],
                ];
            }

            // Count total members for pagination
            $totalQuery = "SELECT COUNT(*) as total FROM member";
            if (count($conditions) > 0) {
                $totalQuery .= ' WHERE ' . implode(' AND ', $conditions);
            }
            $total = $db->query($totalQuery, array_slice($params, 0, count($params) - 2))->getRow()->total; // Remove limit and offset params for count query

            // Return the paginated response
            return $this->respondWithSuccess('Members retrieved successfully.', [
                'members' => $response,
                'total' => $total,
                'limit' => (int) $limit,
                'page' => (int) $page,
            ]);
        } catch (DatabaseException $e) {
            return $this->respondWithError('Failed to retrieve members: ' . $e->getMessage());
        }
    }

}


