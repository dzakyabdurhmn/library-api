<?php

namespace App\Controllers;

use CodeIgniter\Database\Exceptions\DatabaseException;


class AuthorController extends AuthorizationController
{
    public function create()
    {
        $db = \Config\Database::connect();

        $tokenValidation = $this->validateToken('superadmin'); // Fungsi helper dipanggil

        if ($tokenValidation !== true) {
            return $this->respond($tokenValidation, $tokenValidation['status']);
        }







        // Validasi input
        $rules = [
            'name' => [
                'rules' => 'required|min_length[1]|max_length[255]',
                'errors' => [
                    'required' => 'Nama wajib diisi.',
                    'min_length' => 'Nama harus minimal 1 karakter.',
                    'max_length' => 'Nama tidak boleh lebih dari 255 karakter.'
                ]
            ],
            'biography' => [
                'rules' => 'required',
                'errors' => [
                    'required' => 'Biografi wajib diisi.'
                ]
            ]
        ];

        if (!$this->validate($rules)) {
            return $this->respondWithValidationError('Validasi error', $this->validator->getErrors());
        }


        // Ambil data dari request
        $data = [
            'author_name' => $this->request->getVar('name'),
            'author_biography' => $this->request->getVar('biography')
        ];

        try {
            // Query untuk insert data ke tabel author
            $query = "INSERT INTO author (author_name, author_biography) VALUES (?, ?)";
            $db->query($query, array_values($data));

            return $this->respondWithSuccess('Berhasil menambahkan data author.');
        } catch (\Exception $e) {
            return $this->respondWithError('Terdapat kesalahan di sisi server:: ' . $e->getMessage());
        }
    }

    // Fungsi untuk mendapatkan semua penulis dengan pagination, search, dan filter (Read)
    public function index()
    {
        $db = \Config\Database::connect();

        $tokenValidation = $this->validateToken('superadmin,warehouse,frontliner');

        if ($tokenValidation !== true) {
            return $this->respond($tokenValidation, $tokenValidation['status']);
        }

        // Get parameters from query string
        $limit = $this->request->getVar('limit') ?? 10;
        $page = $this->request->getVar('page') ?? 1;
        $search = $this->request->getVar('search');
        $sort = $this->request->getVar('sort');
        $filters = $this->request->getVar('filter') ?? [];
        $enablePagination = $this->request->getVar('pagination') ?? 'true'; // Enable or disable pagination

        // Calculate offset for pagination
        $offset = ($page - 1) * $limit;

        try {
            // Start building the query
            $query = "SELECT * FROM author";
            $conditions = [];
            $params = [];

            // Handle search across all columns (e.g., author_name and author_biography)
            if ($search) {
                $conditions[] = "(author_name LIKE ? OR author_biography LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }

            // Define mapping for additional filters
            $filterMapping = [
                'id' => 'author_id',
                'name' => 'author_name',
                'biography' => 'author_biography',
            ];

            // Handle additional filters
            foreach ($filters as $key => $value) {
                if (!empty($value) && array_key_exists($key, $filterMapping)) {
                    $conditions[] = "{$filterMapping[$key]} = ?";
                    $params[] = $value;
                }
            }

            // Add conditions to the query
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

            // Handle pagination
            if ($enablePagination === 'true') {
                // Add limit and offset for pagination
                $query .= " LIMIT ? OFFSET ?";
                $params[] = (int) $limit;
                $params[] = (int) $offset;
            }

            // Execute query to get author data
            $authors = $db->query($query, $params)->getResultArray();

            // Format the result
            $result = [];
            foreach ($authors as $author) {
                $result[] = [
                    'id' => (int) $author['author_id'],
                    'name' => $author['author_name'],
                    'biography' => $author['author_biography'],
                ];
            }

            // If pagination is enabled, calculate pagination details
            $pagination = new \stdClass();
            if ($enablePagination === 'true') {
                // Query total authors for pagination
                $totalQuery = "SELECT COUNT(*) as total FROM author";
                if (count($conditions) > 0) {
                    $totalQuery .= ' WHERE ' . implode(' AND ', $conditions);
                }
                // Exclude the LIMIT and OFFSET parameters from total count
                $totalParams = array_slice($params, 0, count($params) - 2);
                $total = $db->query($totalQuery, $totalParams)->getRow()->total;

                // Calculate pagination details
                $jumlah_page = ceil($total / $limit);
                $prev = ($page > 1) ? $page - 1 : null;
                $next = ($page < $jumlah_page) ? $page + 1 : null;
                $start = ($page - 1) * $limit + 1;
                $end = min($page * $limit, $total);
                $detail = range(max(1, $page - 2), min($jumlah_page, $page + 2));

                $pagination = [
                    'total_data' => (int) $total,
                    'jumlah_page' => (int) $jumlah_page,
                    'prev' => $prev,
                    'page' => (int) $page,
                    'next' => $next,
                    'detail' => $detail,
                    'start' => $start,
                    'end' => $end,
                ];
            }

            // Return response
            return $this->respondWithSuccess('Berhasil mendapatkan data author.', [
                'data' => $result,
                'pagination' => $pagination
            ]);

        } catch (DatabaseException $e) {
            return $this->respondWithError('Terdapat kesalahan di sisi server: ' . $e->getMessage());
        }
    }





    // Fungsi untuk mendapatkan penulis berdasarkan ID (Read)
    public function get_detail()
    {
        $db = \Config\Database::connect();
        $id = $this->request->getVar('id'); // Get ID from query parameter


        if (!$id) {
            return $this->respondWithValidationError('Membutuhkan parameter ID.', );
        }

        $tokenValidation = $this->validateToken('superadmin,warehouse,frontliner'); // Call the helper function

        if ($tokenValidation !== true) {
            return $this->respond($tokenValidation, $tokenValidation['status']);
        }

        try {
            $query = "SELECT * FROM author WHERE author_id = ?";
            $author = $db->query($query, [$id])->getRowArray();

            $data = [

            ];

            if (!$author) {
                return $this->respondWithSuccess('Data tidak tersedia.', $data);
            }


            $data = [
                'data' => [
                    'id' => (int) $author['author_id'],
                    'name' => $author['author_name'],
                    'biography' => $author['author_biography'] // Access biography correctly
                ],
            ];

            return $this->respondWithSuccess('Berhasil mendapatkan detail author.', $data);
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            return $this->respondWithError('Terdapat kesalahan di sisi server:: ' . $e->getMessage());
        }
    }


    // Fungsi untuk memperbarui data penulis (Update)
    public function update_author()
    {
        $db = \Config\Database::connect();

        $tokenValidation = $this->validateToken('superadmin'); // Fungsi helper dipanggil
        $id = $this->request->getVar('id');

        if ($tokenValidation !== true) {
            return $this->respond($tokenValidation, $tokenValidation['status']);
        }
        $rules = [
            'id' => [
                'rules' => 'required',
                'errors' => [
                    'required' => 'ID wajib diisi.'
                ]
            ],
            'name' => [
                'rules' => 'required|min_length[1]|max_length[255]',
                'errors' => [
                    'required' => 'Nama wajib diisi.',
                    'min_length' => 'Nama harus minimal 1 karakter.',
                    'max_length' => 'Nama tidak boleh lebih dari 255 karakter.'
                ]
            ],
            'biography' => [
                'rules' => 'required',
                'errors' => [
                    'required' => 'Biografi wajib diisi.'
                ]
            ]
        ];

        if (!$this->validate($rules)) {
            return $this->respondWithValidationError('Validasi error', $this->validator->getErrors());
        }



        // Cek apakah penulis dengan ID tersebut ada
        $query = "SELECT COUNT(*) as count FROM author WHERE author_id = ?";
        $exists = $db->query($query, [$id])->getRow()->count;

        if ($exists == 0) {
            return $this->respondWithError('Auhor tidak di temukan.', null, 404);
        }

        $data = [
            'author_name' => $this->request->getVar('name'),
            'author_biography' => $this->request->getVar('biography')
        ];

        try {
            $query = "UPDATE author SET 
                      author_name = COALESCE(?, author_name), 
                      author_biography = COALESCE(?, author_biography) 
                      WHERE author_id = ?";

            $db->query($query, array_merge(array_values($data), [$id]));


            $query = "SELECT * FROM author WHERE author_id = ?";
            $params = [$id]; // Menyediakan parameter untuk menggantikan tanda tanya
            $result = $db->query($query, $params)->getRow(); // Mengambil hasil query

            $data = [
                'data' => [
                    'id' => $result->author_id,
                    'name' => $result->author_name,
                    'biography' => $result->author_biography

                ]
            ];

            return $this->respondWithSuccess('Berhasil mengupdate data author.', $data);
        } catch (DatabaseException $e) {
            return $this->respondWithError('Terdapat kesalahan di sisi server: ' . $e->getMessage());
        }
    }

    // Fungsi untuk menghapus penulis (Delete)
    public function delete_author()
    {
        $db = \Config\Database::connect();
        $id = $this->request->getVar(index: 'id'); // Default limit = 10


        $tokenValidation = $this->validateToken('superadmin'); // Fungsi helper dipanggil

        if ($tokenValidation !== true) {
            return $this->respond($tokenValidation, $tokenValidation['status']);
        }




        try {
            // Cek apakah penulis dengan ID tersebut ada
            $query = "SELECT COUNT(*) as count FROM author WHERE author_id = ?";
            $exists = $db->query($query, [$id])->getRow()->count;

            if ($exists == 0) {
                return $this->respondWithError('Author tidak di temukan.', null, 404);
            }

            // Cek apakah penulis sedang digunakan di tabel buku
            $bookCount = $db->query("SELECT COUNT(*) as count FROM books WHERE books_author_id = ?", [$id])->getRow()->count;

            if ($bookCount > 0) {
                return $this->respondWithError('Gagal menghapus penulis: Penulis ini saat ini terkait dengan buku dan tidak dapat dihapus.', null, 400);
            }

            // Lakukan penghapusan data
            $query = "DELETE FROM author WHERE author_id = ?";
            $db->query($query, [$id]);

            return $this->respondWithSuccess('Berhasil menghapus data author.');
        } catch (DatabaseException $e) {
            return $this->respondWithError('Terdapat kesalahan di sisi server: ' . $e->getMessage());
        }
    }

}
