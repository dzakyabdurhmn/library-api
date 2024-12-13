<?php

namespace App\Controllers;

use CodeIgniter\Database\Exceptions\DatabaseException;

class MemberController extends AuthorizationController
{
    // Fungsi untuk menambahkan member baru (Create)
    public function create()
    {


        $tokenValidation = $this->validateToken('superadmin,frontliner'); // Fungsi helper dipanggil

        if ($tokenValidation !== true) {
            return $this->respond($tokenValidation, $tokenValidation['status']);
        }

        $rules = [
            'institution' => [
                'rules' => 'required|min_length[5]',
                'errors' => [
                    'required' => 'Institution wajib diisi.',
                    'min_length' => 'Institution harus minimal 5 karakter.'
                ]
            ],
            'email' => [
                'rules' => 'required|valid_email',
                'errors' => [
                    'required' => 'Email wajib diisi.',
                    'valid_email' => 'Format email tidak valid.'
                ]
            ],
            'full_name' => [
                'rules' => 'required',
                'errors' => [
                    'required' => 'Nama lengkap wajib diisi.'
                ]
            ],
            'address' => [
                'rules' => 'required',
                'errors' => [
                    'required' => 'Alamat wajib diisi.'
                ]
            ],
            'job' => [
                'rules' => 'required',
                'errors' => [
                    'required' => 'Pekerjaan wajib diisi.'
                ]
            ],
            'status' => [
                'rules' => 'in_list[active,inactive]',
                'errors' => [
                    'in_list' => 'Status harus salah satu dari: active, inactive.'
                ]
            ],
            'religion' => [
                'rules' => 'required',
                'errors' => [
                    'required' => 'Agama wajib diisi.'
                ]
            ],
            'barcode' => [
                'rules' => 'required',
                'errors' => [
                    'required' => 'Barcode wajib diisi.'
                ]
            ],
            'gender' => [
                'rules' => 'required|in_list[PRIA,WANITA]',
                'errors' => [
                    'required' => 'Jenis kelamin wajib diisi.',
                    'in_list' => 'Jenis kelamin harus salah satu dari: PRIA atau WANITA.'
                ]
            ]
        ];

        if (!$this->validate($rules)) {
            return $this->respondWithValidationError('Validasi error', $this->validator->getErrors());
        }



        $data = [
            'member_institution' => $this->request->getVar('institution'),
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
            $query = "INSERT INTO member (member_institution, member_email, member_full_name, member_address, member_job, member_status, member_religion, member_barcode, member_gender) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $this->db->query($query, [
                $data['member_institution'],
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
            $member = $this->db->query($query, [$id])->getRowArray();




            $data = [

            ];

            if (!$member) {
                return $this->respondWithSuccess('Data tidak tersedia.', $data);
            }


            $response = [
                'data' => [
                    'id' => $member['member_id'],
                    'institution' => $member['member_institution'],
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


    public function update_member()
    {


        $id = $this->request->getVar('id'); // Get ID from query parameter

        $tokenValidation = $this->validateToken('superadmin,frontliner'); // Fungsi helper dipanggil

        if ($tokenValidation !== true) {
            return $this->respond($tokenValidation, $tokenValidation['status']);
        }

        $rules = [
            'id' => [
                'rules' => 'required|is_natural_no_zero',
                'errors' => [
                    'required' => 'ID wajib diisi.',
                    'is_natural_no_zero' => 'ID harus berupa angka positif yang valid.'
                ]
            ],
            'institution' => [
                'rules' => 'min_length[5]',
                'errors' => [
                    'min_length' => 'institution harus minimal 5 karakter.'
                ]
            ],
            'email' => [
                'rules' => 'valid_email',
                'errors' => [
                    'valid_email' => 'Format email tidak valid.'
                ]
            ],
            'full_name' => [
                'rules' => 'min_length[3]',
                'errors' => [
                    'min_length' => 'Nama lengkap harus minimal 3 karakter.'
                ]
            ],
            'address' => [
                'rules' => 'min_length[3]',
                'errors' => [
                    'min_length' => 'Alamat harus minimal 3 karakter.'
                ]
            ],
            'job' => [
                'rules' => 'min_length[3]',
                'errors' => [
                    'min_length' => 'Pekerjaan harus minimal 3 karakter.'
                ]
            ],
            'status' => [
                'rules' => 'in_list[active,inactive]',
                'errors' => [
                    'in_list' => 'Status harus salah satu dari: active, inactive.'
                ]
            ],
            'religion' => [
                'rules' => 'min_length[3]',
                'errors' => [
                    'min_length' => 'Agama harus minimal 3 karakter.'
                ]
            ],
            'barcode' => [
                'rules' => 'min_length[3]',
                'errors' => [
                    'min_length' => 'Barcode harus minimal 3 karakter.'
                ]
            ],
            'gender' => [
                'rules' => 'in_list[PRIA,WANITA]',
                'errors' => [
                    'in_list' => 'Jenis kelamin harus salah satu dari: PRIA atau WANITA.'
                ]
            ],
        ];

        if (!$this->validate($rules)) {
            return $this->respondWithValidationError('Validasi error', $this->validator->getErrors());
        }

        // Cek apakah member dengan ID tersebut ada
        $query = "SELECT COUNT(*) as count FROM member WHERE member_id = ?";
        $exists = $this->db->query($query, [$id])->getRow()->count;

        if ($exists == 0) {
            return $this->respondWithError('Member tidak ditemukan.', null, 404);
        }

        $data = [
            'member_institution' => $this->request->getVar('institution'),
            'member_email' => $this->request->getVar('email'),
            'member_full_name' => $this->request->getVar('full_name'),
            'member_address' => $this->request->getVar('address'),
            'member_job' => $this->request->getVar('job'),
            'member_status' => $this->request->getVar('status'),
            'member_religion' => $this->request->getVar('religion'),
            'member_barcode' => $this->request->getVar('barcode'),
            'member_gender' => $this->request->getVar('gender')
        ];

        try {
            $setClauses = [];
            $params = [];
            foreach ($data as $field => $value) {
                if ($value !== null) {
                    $setClauses[] = "$field = COALESCE(?, $field)";
                    $params[] = $value;
                }
            }
            $params[] = $id;

            if (empty($setClauses)) {
                return $this->respondWithError('Tidak ada data yang diberikan untuk diperbarui.');
            }

            $query = "UPDATE member SET " . implode(', ', $setClauses) . " WHERE member_id = ?";

            $this->db->query($query, $params);

            $query = "SELECT * FROM member WHERE member_id = ?";
            $member = $this->db->query($query, [$id])->getRowArray();

            $data = [
                'data' => [
                    'id' => $member['member_id'],
                    'institution' => $member['member_institution'],
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

            return $this->respondWithSuccess('Berhasil mengupdate data member', $data);
        } catch (DatabaseException $e) {
            return $this->respondWithError('Terdapat kesalahan di sisi server: ' . $e->getMessage());
        }
    }


    // Fungsi untuk menghapus member berdasarkan member_id (Delete)
    public function delete_member()
    {

        $id = $this->request->getVar('id');

        // Validate token
        $tokenValidation = $this->validateToken('superadmin,frontliner');
        if ($tokenValidation !== true) {
            return $this->respond($tokenValidation, $tokenValidation['status']);
        }

        try {
            // Check if member exists
            $query = "SELECT COUNT(*) as count FROM member WHERE member_id = ?";
            $exists = $this->db->query($query, [$id])->getRow()->count;

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
            $loanCount = $this->db->query($loanQuery, [$id])->getRow()->loan_count;

            if ($loanCount > 0) {
                return $this->respondWithError('Member ini sedang meminjam buku dan tidak bisa dihapus.', null, 400);
            }

            // Proceed to delete the member
            $deleteQuery = "DELETE FROM member WHERE member_id = ?";
            $this->db->query($deleteQuery, [$id]);

            return $this->respondWithSuccess('berhasil menghapus member.');
        } catch (DatabaseException $e) {
            return $this->respondWithError('Terjadi kesalahan di sisi server: ' . $e->getMessage());
        }
    }



    // Fungsi untuk mendapatkan semua member (List)
    // Fungsi untuk mendapatkan semua member dengan pagination, search, dan filter
    public function index()
    {


        $tokenValidation = $this->validateToken('superadmin,frontliner');
        if ($tokenValidation !== true) {
            return $this->respond($tokenValidation, $tokenValidation['status']);
        }

        // Get parameters from query string
        $limit = $this->request->getVar('limit') ?? 10;
        $page = $this->request->getVar('page') ?? 1;
        $search = $this->request->getVar('search');
        $sort = $this->request->getVar('sort');
        $filters = $this->request->getVar('filter') ?? [];
        $enablePagination = $this->request->getVar('pagination') !== 'false'; // Enable pagination if not 'false'

        // Calculate offset for pagination
        $offset = ($page - 1) * $limit;

        try {
            // Base query
            $query = "SELECT member_id, member_institution, member_email, member_full_name, member_address, member_job, member_status, member_religion, member_barcode, member_gender FROM member";
            $conditions = [];
            $params = [];

            // Handle search across all fields
            if ($search) {
                $conditions[] = "(member_id = ? OR member_institution LIKE ? OR member_full_name LIKE ? OR member_email LIKE ? OR member_address LIKE ? OR member_job LIKE ? OR member_status LIKE ? OR member_religion LIKE ? OR member_barcode LIKE ? OR member_gender LIKE ?)";
                $params[] = $search; // Mencari berdasarkan ID
                $params = array_merge($params, array_fill(0, 9, "%$search%")); // Mencari di kolom lainnya
            }

            // Map filter keys to database columns
            $filterMapping = [
                'id' => 'member_id',
                'institution' => 'member_institution',
                'email' => 'member_email',
                'full_name' => 'member_full_name',
                'address' => 'member_address',
                'job' => 'member_job',
                'status' => 'member_status',
                'religion' => 'member_religion',
                'barcode' => 'member_barcode',
                'gender' => 'member_gender',
            ];


            if (!empty($sort)) {
                $sortField = ltrim($sort, '-');
                $sortDirection = $sort[0] === '-' ? 'DESC' : 'ASC';
                if (array_key_exists($sortField, $filterMapping)) {
                    $query .= " ORDER BY {$filterMapping[$sortField]} $sortDirection";
                }
            }


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
            $members = $this->db->query($query, $params)->getResultArray();

            // Prepare response to include all required fields
            $response = array_map(function ($member) {
                return [
                    'id' => (int) $member['member_id'],
                    'institution' => $member['member_institution'],
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
                $total = $this->db->query($totalQuery, array_slice($params, 0, count($params) - 2))->getRow()->total;

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





