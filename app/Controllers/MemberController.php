<?php

namespace App\Controllers;

use CodeIgniter\Database\Exceptions\DatabaseException;

class MemberController extends AuthorizationController
{
    // Fungsi untuk menambahkan member baru (Create)
    public function create()
    {
        $db = \Config\Database::connect();

        $tokenValidation = $this->validateToken('superadmin,frontliner'); // Fungsi helper dipanggil

        if ($tokenValidation !== true) {
            return $this->respond($tokenValidation, $tokenValidation['status']);
        }
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
            return $this->respondWithValidationError('Validasi error', $this->validator->getErrors());
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

            return $this->respondWithSuccess('Behasil menambahkan data member.');
        } catch (DatabaseException $e) {
            return $this->respondWithError('Terdapat kesalahan di sisi server: ' . $e->getMessage());
        }
    }

    // Fungsi untuk mendapatkan detail member berdasarkan member_id (Read)
    public function get_detail()
    {
        $db = \Config\Database::connect();

        $id = $this->request->getVar('id'); // Get ID from query parameter


        if (!$id) {
            return $this->respondWithValidationError('Parameter id di perlukan', );
        }


        $tokenValidation = $this->validateToken('superadmin,frontliner'); // Fungsi helper dipanggil

        if ($tokenValidation !== true) {
            return $this->respond($tokenValidation, $tokenValidation['status']);
        }

        try {
            // Raw query untuk mendapatkan data member berdasarkan member_id
            $query = "SELECT * FROM member WHERE member_id = ?";
            $member = $db->query($query, [$id])->getRowArray();




            if (!$member) {
                return $this->respondWithNotFound('Member di temukan');
            }

            $response = [
                'data' => [
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
                ]
            ];


            return $this->respondWithSuccess('Member di temukan', $response);
        } catch (DatabaseException $e) {
            return $this->respondWithError('Terdapat kesalahan di sisi server: ' . $e->getMessage());
        }
    }

    // Fungsi untuk memperbarui data member (Update)

    public function update($id = null)
    {
        $db = \Config\Database::connect();


        $tokenValidation = $this->validateToken('superadmin,frontliner'); // Fungsi helper dipanggil

        if ($tokenValidation !== true) {
            return $this->respond($tokenValidation, $tokenValidation['status']);
        }

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
            return $this->respondWithError('Tidak ada data yang diberikan untuk diperbarui.');
        }

        // Validate only the fields that are present in the request
        if (!$this->validate(array_intersect_key($validationRules, $validationData))) {
            return $this->respondWithValidationError('Validasi error', $this->validator->getErrors());
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
            return $this->respondWithSuccess('Berhasil mengupdate data member', $response);

        } catch (DatabaseException $e) {
            // Return error response if there's a database error
            return $this->respondWithError('Terdapat kesalahan di sisi server: ' . $e->getMessage());
        }
    }



    // Fungsi untuk menghapus member berdasarkan member_id (Delete)
    public function delete_member()
    {
        $db = \Config\Database::connect();
        $id = $this->request->getVar('id');

        // Validate token
        $tokenValidation = $this->validateToken('superadmin,frontliner');
        if ($tokenValidation !== true) {
            return $this->respond($tokenValidation, $tokenValidation['status']);
        }

        try {
            // Check if member exists
            $query = "SELECT COUNT(*) as count FROM member WHERE member_id = ?";
            $exists = $db->query($query, [$id])->getRow()->count;

            if ($exists == 0) {
                return $this->respondWithError('Member tidak ditemukan.', null, 404);
            }

            // Check if the member has any active loans
            $loanQuery = "
            SELECT COUNT(*) as loan_count
            FROM loan l
            JOIN loan_detail ld ON l.loan_transaction_code = ld.loan_detail_loan_transaction_code
            WHERE l.loan_member_id = ? AND ld.loan_detail_status = 'Borrowed'
        ";
            $loanCount = $db->query($loanQuery, [$id])->getRow()->loan_count;

            if ($loanCount > 0) {
                return $this->respondWithError('Member ini sedang meminjam buku dan tidak bisa dihapus.', null, 400);
            }

            // Proceed to delete the member
            $deleteQuery = "DELETE FROM member WHERE member_id = ?";
            $db->query($deleteQuery, [$id]);

            return $this->respondWithSuccess('berhasil menghapus member.');
        } catch (DatabaseException $e) {
            return $this->respondWithError('Terjadi kesalahan di sisi server: ' . $e->getMessage());
        }
    }



    // Fungsi untuk mendapatkan semua member (List)
    // Fungsi untuk mendapatkan semua member dengan pagination, search, dan filter
    public function index()
    {
        $db = \Config\Database::connect();

        $tokenValidation = $this->validateToken('superadmin,frontliner');
        if ($tokenValidation !== true) {
            return $this->respond($tokenValidation, $tokenValidation['status']);
        }

        // Get parameters from query string
        $limit = $this->request->getVar('limit') ?? 10;
        $page = $this->request->getVar('page') ?? 1;
        $search = $this->request->getVar('search');
        $filters = $this->request->getVar('filter') ?? [];
        $enablePagination = $this->request->getVar('pagination') !== 'false'; // Enable pagination if not 'false'

        // Calculate offset for pagination
        $offset = ($page - 1) * $limit;

        try {
            // Base query
            $query = "SELECT member_id, member_username, member_email, member_full_name, member_address, member_job, member_status, member_religion, member_barcode, member_gender FROM member";
            $conditions = [];
            $params = [];

            // Handle search across all fields
            if ($search) {
                $conditions[] = "(member_id = ? OR member_username LIKE ? OR member_full_name LIKE ? OR member_email LIKE ? OR member_address LIKE ? OR member_job LIKE ? OR member_status LIKE ? OR member_religion LIKE ? OR member_barcode LIKE ? OR member_gender LIKE ?)";
                $params[] = (int) $search; // Mencari berdasarkan ID
                $params = array_merge($params, array_fill(0, 9, "%$search%")); // Mencari di kolom lainnya
            }

            // Map filter keys to database columns
            $filterMapping = [
                'id' => 'member_id',
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

            // Apply filters
            foreach ($filters as $key => $value) {
                if (array_key_exists($key, $filterMapping)) {
                    $conditions[] = "{$filterMapping[$key]} = ?";
                    $params[] = $value;
                }
            }

            // Apply conditions to query
            if (!empty($conditions)) {
                $query .= ' WHERE ' . implode(' AND ', $conditions);
            }

            // Handle pagination (if enabled)
            if ($enablePagination) {
                $query .= " LIMIT ? OFFSET ?";
                $params[] = (int) $limit;
                $params[] = (int) $offset;
            }

            // Execute query and fetch results
            $members = $db->query($query, $params)->getResultArray();

            // Prepare response to include all required fields
            $response = array_map(function ($member) {
                return [
                    'id' => (int) $member['member_id'],
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
            }, $members);

            // Count total members for pagination if enabled
            $pagination = [];
            if ($enablePagination) {
                $totalQuery = "SELECT COUNT(*) as total FROM member";
                if (!empty($conditions)) {
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

            // Return response
            return $this->respondWithSuccess('Members retrieved successfully.', [
                'data' => $response,
                'pagination' => $pagination
            ]);

        } catch (DatabaseException $e) {
            return $this->respondWithError('Failed to retrieve members: ' . $e->getMessage());
        }
    }





}


