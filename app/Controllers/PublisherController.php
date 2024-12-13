<?php

namespace App\Controllers;

use CodeIgniter\Database\Exceptions\DatabaseException;

class PublisherController extends AuthorizationController
{
    // Fungsi untuk menambahkan penerbit (Create)
    public function create()
    {


        $tokenValidation = $this->validateToken('superadmin'); // Fungsi helper dipanggil

        if ($tokenValidation !== true) {
            return $this->respond($tokenValidation, $tokenValidation['status']);
        }


        $rules = [
            'name' => [
                'rules' => 'required|min_length[1]',
                'errors' => [
                    'required' => 'Nama wajib diisi.',
                    'min_length' => 'Nama harus minimal 1 karakter.'
                ]
            ],
            'address' => [
                'rules' => 'required|min_length[1]',
                'errors' => [
                    'required' => 'Alamat wajib diisi.',
                    'min_length' => 'Alamat harus minimal 1 karakter.'
                ]
            ],
            'phone' => [
                'rules' => 'required|min_length[1]',
                'errors' => [
                    'required' => 'Nomor telepon wajib diisi.',
                    'min_length' => 'Nomor telepon harus minimal 1 karakter.'
                ]
            ],
            'email' => [
                'rules' => 'required|valid_email',
                'errors' => [
                    'required' => 'Email wajib diisi.',
                    'valid_email' => 'Format email tidak valid.'
                ]
            ]
        ];

        if (!$this->validate($rules)) {
            return $this->respondWithValidationError('Validasi error', $this->validator->getErrors());
        }


        $data = [
            'publisher_name' => $this->request->getVar('name'),
            'publisher_address' => $this->request->getVar('address'),
            'publisher_phone' => $this->request->getVar('phone'),
            'publisher_email' => $this->request->getVar('email')
        ];

        try {
            $query = "INSERT INTO publisher (publisher_name, publisher_address, publisher_phone, publisher_email) VALUES (?, ?, ?, ?)";
            $this->db->query($query, array_values($data));

            return $this->respondWithSuccess('Berhasil mengupdate data publiser.');
        } catch (DatabaseException $e) {
            return $this->respondWithError('Terdapat kesalahan di sisi server:: ' . $e->getMessage());
        }
    }

    // Fungsi untuk mendapatkan semua penerbit dengan pagination, search, dan filter (Read)

    public function index()
    {


        $tokenValidation = $this->validateToken('superadmin,warehouse,frontliner');

        if ($tokenValidation !== true) {
            return $this->respond($tokenValidation, $tokenValidation['status']);
        }

        $limit = $this->request->getVar('limit') ?? 10;
        $page = $this->request->getVar('page') ?? 1;
        $search = $this->request->getVar('search');
        $filters = $this->request->getVar('filter') ?? [];
        $enablePagination = filter_var($this->request->getVar('pagination'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
        $sort = $this->request->getVar('sort') ?? ''; // New sorting parameter

        $offset = ($page - 1) * $limit;

        try {
            $query = "SELECT publisher_id, publisher_name, publisher_address, publisher_phone, publisher_email FROM publisher";
            $conditions = [];
            $params = [];

            if ($search) {
                $conditions[] = "(publisher_name LIKE ? OR publisher_address LIKE ? OR publisher_phone LIKE ? OR publisher_email LIKE ?)";
                $searchTerm = "%$search%";
                $params = array_fill(0, 4, $searchTerm);
            }

            $filterMapping = [
                'id' => 'publisher_id',
                'name' => 'publisher_name',
                'address' => 'publisher_address',
                'phone' => 'publisher_phone',
                'email' => 'publisher_email',
            ];

            foreach ($filters as $key => $value) {
                if (array_key_exists($key, $filterMapping)) {
                    $dbField = $filterMapping[$key];
                    if (is_array($value)) {
                        $conditions[] = "$dbField IN (" . implode(',', array_fill(0, count($value), '?')) . ")";
                        $params = array_merge($params, $value);
                    } else {
                        $conditions[] = "$dbField = ?";
                        $params[] = $value;
                    }
                }
            }

            if (count($conditions) > 0) {
                $query .= ' WHERE ' . implode(' AND ', $conditions);
            }

            // Handle sorting
            if (!empty($sort)) {
                $sortField = ltrim($sort, '-');
                $sortDirection = $sort[0] === '-' ? 'DESC' : 'ASC';
                if (array_key_exists($sortField, $filterMapping)) {
                    $query .= " ORDER BY {$filterMapping[$sortField]} $sortDirection";
                }
            }

            if ($enablePagination) {
                $query .= " LIMIT ? OFFSET ?";
                $params[] = (int) $limit;
                $params[] = (int) $offset;
            }

            $publishers = $this->db->query($query, $params)->getResultArray();

            $result = [];
            foreach ($publishers as $publisher) {
                $result[] = [
                    'id' => (int) $publisher['publisher_id'],
                    'name' => $publisher['publisher_name'],
                    'address' => $publisher['publisher_address'],
                    'phone' => $publisher['publisher_phone'],
                    'email' => $publisher['publisher_email']
                ];
            }

            $pagination = new \stdClass();
            if ($enablePagination) {
                $totalQuery = "SELECT COUNT(*) as total FROM publisher";
                if (count($conditions) > 0) {
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

            return $this->respondWithSuccess('Berhasil mendapatkan data publisher.', [
                'data' => $result,
                'pagination' => $pagination
            ]);

        } catch (DatabaseException $e) {
            return $this->respondWithError('Terdapat kesalahan di sisi server: ' . $e->getMessage());
        }
    }



    // Fungsi untuk mendapatkan penerbit berdasarkan ID (Read)
    public function get_detail()
    {


        $id = $this->request->getVar('id'); // Get ID from query parameter


        if (!$id) {
            return $this->respondWithValidationError('Parameter ID di perlukan', );
        }

        $tokenValidation = $this->validateToken('superadmin,warehouse,frontliner'); // Fungsi helper dipanggil

        if ($tokenValidation !== true) {
            return $this->respond($tokenValidation, $tokenValidation['status']);
        }

        try {
            $query = "SELECT * FROM publisher WHERE publisher_id = ?";
            $publisher = $this->db->query($query, [$id])->getRowArray();



            $data = [

            ];

            if (!$publisher) {
                return $this->respondWithSuccess('Data tidak tersedia.', $data);
            }

            $result = [
                'data' => [
                    'id' => $publisher['publisher_id'],
                    'name' => $publisher['publisher_name'],
                    'address' => $publisher['publisher_address'],
                    'phone' => $publisher['publisher_phone'],
                    'email' => $publisher['publisher_email'],
                ]
            ];

            if (!$publisher) {
                return $this->respondWithNotFound('Publiser tidak di temukan.');
            }

            return $this->respondWithSuccess('Publisher di temukan.', $result);
        } catch (DatabaseException $e) {
            return $this->respondWithError('Terdapat kesalahan di sisi server:: ' . $e->getMessage());
        }
    }

    // Fungsi untuk memperbarui data penerbit (Update)
    public function update_publiser()
    {


        $id = $this->request->getVar('id'); // Get ID from query parameter



        $tokenValidation = $this->validateToken('superadmin'); // Fungsi helper dipanggil

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
            'name' => [
                'rules' => 'required|min_length[1]',
                'errors' => [
                    'required' => 'Nama wajib diisi.',
                    'min_length' => 'Nama harus minimal 1 karakter.'
                ]
            ],
            'address' => [
                'rules' => 'required|min_length[1]',
                'errors' => [
                    'required' => 'Alamat wajib diisi.',
                    'min_length' => 'Alamat harus minimal 1 karakter.'
                ]
            ],
            'phone' => [
                'rules' => 'required|min_length[1]',
                'errors' => [
                    'required' => 'Nomor telepon wajib diisi.',
                    'min_length' => 'Nomor telepon harus minimal 1 karakter.'
                ]
            ],
            'email' => [
                'rules' => 'required|valid_email',
                'errors' => [
                    'required' => 'Email wajib diisi.',
                    'valid_email' => 'Format email tidak valid.'
                ]
            ]
        ];

        if (!$this->validate($rules)) {
            return $this->respondWithValidationError('Validasi error', $this->validator->getErrors());
        }




        // Cek apakah penerbit dengan ID tersebut ada
        $query = "SELECT COUNT(*) as count FROM publisher WHERE publisher_id = ?";
        $exists = $this->db->query($query, [$id])->getRow()->count;

        if ($exists == 0) {
            return $this->respondWithError('Publiser tidak di temukan.', null, 404);
        }

        $data = [
            'publisher_name' => $this->request->getVar('name'),
            'publisher_address' => $this->request->getVar('address'),
            'publisher_phone' => $this->request->getVar('phone'),
            'publisher_email' => $this->request->getVar('email')
        ];

        try {
            $query = "UPDATE publisher SET 
                      publisher_name = COALESCE(?, publisher_name), 
                      publisher_address = COALESCE(?, publisher_address), 
                      publisher_phone = COALESCE(?, publisher_phone), 
                      publisher_email = COALESCE(?, publisher_email) 
                      WHERE publisher_id = ?";

            $this->db->query($query, array_merge(array_values($data), [$id]));


            $query = "SELECT * FROM publisher WHERE publisher_id = ?";
            $publisher = $this->db->query($query, [$id])->getRowArray();

            $data = [
                'data' => [
                    'id' => $publisher['publisher_id'],
                    'name' => $publisher['publisher_name'],
                    'address' => $publisher['publisher_address'],
                    'phone' => $publisher['publisher_phone'],
                    'email' => $publisher['publisher_email']
                ]
            ];




            return $this->respondWithSuccess('Berhasil mengupdate data publiser', $data);
        } catch (DatabaseException $e) {
            return $this->respondWithError('Terdapat kesalahan di sisi server:: ' . $e->getMessage());
        }
    }

    // Fungsi untuk menghapus penerbit (Delete)
    public function delete_publiser()
    {

        $id = $this->request->getVar(index: 'id'); // Default limit = 10


        $tokenValidation = $this->validateToken('superadmin'); // Fungsi helper dipanggil

        if ($tokenValidation !== true) {
            return $this->respond($tokenValidation, $tokenValidation['status']);
        }

        try {
            // Cek apakah penerbit dengan ID tersebut ada
            $query = "SELECT COUNT(*) as count FROM publisher WHERE publisher_id = ?";
            $exists = $this->db->query($query, [$id])->getRow()->count;

            if ($exists == 0) {
                return $this->respondWithError('Failed to delete publisher: Publisher not found.', null, 404);
            }

            // Cek apakah penerbit sedang digunakan di tabel buku
            $bookCount = $this->db->query("SELECT COUNT(*) as count FROM books WHERE books_publisher_id = ?", [$id])->getRow()->count;

            if ($bookCount > 0) {
                return $this->respondWithError('Failed to delete publisher: This publisher is currently associated with books and cannot be deleted.', null, 400);
            }

            // Lakukan penghapusan data
            $query = "DELETE FROM publisher WHERE publisher_id = ?";
            $this->db->query($query, [$id]);

            return $this->respondWithSuccess('Berhasil menghapus data publiser');
        } catch (DatabaseException $e) {
            return $this->respondWithError('Terdapat kesalahan di sisi server: ' . $e->getMessage());
        }
    }
}


