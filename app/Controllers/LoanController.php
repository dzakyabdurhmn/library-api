<?php

namespace App\Controllers;

use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\RESTful\ResourceController;
use Config\Database;

class LoanController extends AuthorizationController
{


    public function borrow_book()
    {
        try {
            $request = $this->request->getJSON(true);

            $tokenValidation = $this->validateToken('frontliner,superadmin');

            if ($tokenValidation !== true) {
                return $this->respond($tokenValidation, $tokenValidation['status']);
            }

            if (empty($request['member_id']) || empty($request['borrow_book'])) {
                return $this->respondWithError("Member ID dan daftar buku diperlukan.", null, 400);
            }

            $memberId = $request['member_id'];
            $borrowBooks = $request['borrow_book'];

            $db = Database::connect();

            // Ambil detail member
            $memberQuery = $db->query("SELECT member_institution, member_email, member_full_name, member_address FROM member WHERE member_id = ?", [$memberId]);
            $member = $memberQuery->getRow();

            if (!$member) {
                return $this->respondWithError("Member tidak ditemukan.", null, 404);
            }

            // Cek total buku yang sedang dipinjam
            $currentLoansQuery = $db->query("
            SELECT COUNT(*) as total 
            FROM loan_detail ld
            JOIN loan l ON l.loan_id = ld.loan_detail_loan_id
            WHERE ld.loan_detail_status = 'Borrowed' 
            AND l.loan_member_id = ?",
                [$memberId]
            );

            $currentLoans = $currentLoansQuery->getRow()->total;

            if ($currentLoans >= 3) {
                return $this->respondWithError("Tidak dapat meminjam lebih dari 3 buku dalam satu waktu", null, 400);
            }

            $booksToBorrow = array_filter($borrowBooks, function ($book) {
                return isset($book['book_id']);
            });

            if (count($booksToBorrow) + $currentLoans > 3) {
                return $this->respondWithError("Hanya dapat meminjam " . (3 - $currentLoans) . " buku lagi.", null, 400);
            }

            $db->transBegin();

            try {
                // Insert ke tabel loan
                $loanData = [
                    'loan_member_id' => $memberId,
                    'loan_date' => date('Y-m-d H:i:s'),
                    'loan_member_institution' => $member->member_institution,
                    'loan_member_email' => $member->member_email,
                    'loan_member_full_name' => $member->member_full_name,
                    'loan_member_address' => $member->member_address
                ];

                $db->table('loan')->insert($loanData);
                $loanId = $db->insertID();



                foreach ($booksToBorrow as $book) {
                    $bookId = $book['book_id'];

                    // Cek ketersediaan buku
                    $bookQuery = $db->query("
                    SELECT b.*, 
                        a.author_name, 
                        a.author_biography,
                        p.publisher_name,
                        p.publisher_address,
                        p.publisher_phone,
                        p.publisher_email
                    FROM books b
                    LEFT JOIN author a ON b.books_author_id = a.author_id
                    LEFT JOIN publisher p ON b.books_publisher_id = p.publisher_id
                    WHERE b.book_id = ? 
                    FOR UPDATE",
                        [$bookId]
                    );

                    $bookData = $bookQuery->getRow();

                    if (!$bookData) {
                        throw new \Exception("Buku dengan ID {$bookId} tidak ditemukan.");
                    }

                    if ($bookData->books_stock_quantity <= 0) {
                        throw new \Exception("Stok buku '{$bookData->books_title}' tidak tersedia.");
                    }

                    // Insert loan detail
                    $loanDetailData = [
                        'loan_detail_loan_id' => $loanId,
                        'loan_detail_book_id' => $bookId,
                        'loan_detail_borrow_date' => date('Y-m-d H:i:s'),
                        'loan_detail_status' => 'Borrowed',
                        'loan_detail_book_title' => $bookData->books_title,
                        'loan_detail_book_isbn' => $bookData->books_isbn,
                        'loan_detail_book_publication_year' => $bookData->books_publication_year,
                        'loan_detail_book_author_name' => $bookData->author_name,
                        'loan_detail_book_author_biography' => $bookData->author_biography, // Menambahkan biografi penulis
                        'loan_detail_book_publisher_name' => $bookData->publisher_name,
                        'loan_detail_book_publisher_address' => $bookData->publisher_address, // Menambahkan alamat penerbit
                        'loan_detail_book_publisher_phone' => $bookData->publisher_phone, // Menambahkan telepon penerbit
                        'loan_detail_book_publisher_email' => $bookData->publisher_email, // Menambahkan email penerbit
                        'loan_detail_book_publisher_id' => $bookData->books_publisher_id,
                        'loan_detail_book_author_id' => $bookData->books_author_id,
                    ];

                    $db->table('loan_detail')->insert($loanDetailData); // Pastikan ini dieksekusi

                    // Update stok buku
                    $result = $db->query("
                    UPDATE books 
                    SET books_stock_quantity = books_stock_quantity - 1 
                    WHERE book_id = ? AND books_stock_quantity > 0",
                        [$bookId]
                    );

                    if ($db->affectedRows() === 0) {
                        throw new \Exception("Gagal mengupdate stok buku '{$bookData->books_title}'.");
                    }
                }



                $db->transCommit();

                return $this->respondWithSuccess("Berhasil meminjam buku", [
                    "loan_id" => $loanId,
                    "member" => [
                        "id" => $memberId,
                        "name" => $member->member_full_name
                    ],
                    "borrowed_books" => count($booksToBorrow)
                ]);

            } catch (\Exception $e) {
                $db->transRollback();
                return $this->respondWithError("Error: " . $e->getMessage(), null, 400);
            }

        } catch (\Exception $e) {
            return $this->respondWithError("Terjadi kesalahan sistem: " . $e->getMessage(), null, 500);
        }
    }

    public function deport()
    {
        $db = Database::connect();
        $tokenValidation = $this->validateToken('superadmin');

        if ($tokenValidation !== true) {
            return $this->respond($tokenValidation, $tokenValidation['status']);
        }

        $request = $this->request->getJSON(true);

        if (empty($request['borrow_id']) || empty($request['borrow_book'])) {
            return $this->respondWithError("ID peminjaman dan detail buku diperlukan.", null, 400);
        }

        $loanId = $request['borrow_id'];
        $returnBooks = $request['borrow_book'];

        // Cek peminjaman dengan raw query
        $loanQuery = $db->query("
        SELECT l.*, m.member_institution, m.member_email, m.member_full_name, m.member_barcode 
        FROM loan l
        JOIN member m ON m.member_id = l.loan_member_id
        WHERE l.loan_id = ?",
            [$loanId]
        );

        $loan = $loanQuery->getRow();

        if (!$loan) {
            return $this->respondWithError("Data peminjaman tidak ditemukan.", null, 404);
        }

        // Ambil data keterlambatan dengan raw query
        $punishmentQuery = $db->query("
        SELECT percentage_object 
        FROM percentage 
        WHERE percentage_name = 'keterlambatan_pengembalian'
        ORDER BY punishment_created_at DESC 
        LIMIT 1"
        );

        $punishmentData = $punishmentQuery->getRow();
        // Parse the JSON object from 'percentage_object'
        $punishmentDaysPerLateDay = isset($punishmentData) ? json_decode($punishmentData->percentage_object)->day : 1;

        $validStatuses = ['Good', 'Borrowed', 'Broken', 'Missing'];
        $maxLoanDays = 7; // Maksimal hari peminjaman
        $totalPunishmentDays = 0;

        // Mulai transaksi
        $db->transStart();

        foreach ($returnBooks as $book) {
            $bookId = $book['book_id'];
            $status = $book['status'];

            if (!in_array($status, $validStatuses)) {
                $db->transRollback();
                return $this->respondWithError(
                    "Status '$status' tidak valid. Status yang diperbolehkan: " . implode(', ', $validStatuses),
                    null,
                    400
                );
            }

            // Cek buku yang dipinjam
            $loanDetailQuery = $db->query("
            SELECT ld.* 
            FROM loan_detail ld
            WHERE ld.loan_detail_loan_id = ? 
            AND ld.loan_detail_book_id = ? 
            AND ld.loan_detail_status = 'Borrowed'",
                [$loanId, $bookId]
            );

            $loanDetail = $loanDetailQuery->getRow();

            if (!$loanDetail) {
                $db->transRollback();
                return $this->respondWithError("Buku ini tidak tercatat dalam peminjaman tersebut.", null, 400);
            }

            // Hitung durasi peminjaman
            $borrowDateTime = new \DateTime($loanDetail->loan_detail_borrow_date);
            $returnDateTime = new \DateTime();
            $interval = $borrowDateTime->diff($returnDateTime);
            $loanPeriod = $interval->days;

            // Hitung hari keterlambatan
            $lateDays = max(0, $loanPeriod - $maxLoanDays);
            $bookPunishmentDays = $lateDays * $punishmentDaysPerLateDay;
            $totalPunishmentDays += $bookPunishmentDays;

            // Prepare update data for loan_detail using raw SQL query
            $updateQuery = "
            UPDATE loan_detail
            SET loan_detail_status = ?, 
                loan_detail_return_date = NOW(),
                loan_detail_period = ?,
                loan_detail_late_days = ?,
                loan_detail_punishment_days = ?
            WHERE loan_detail_loan_id = ? 
            AND loan_detail_book_id = ?
        ";

            $db->query($updateQuery, [
                $status === 'Good' ? 'Returned' : $status,  // Only mark as 'Returned' if status is 'Good'
                $loanPeriod,
                $lateDays,
                $bookPunishmentDays,
                $loanId,
                $bookId
            ]);

            // Jika buku dikembalikan dalam kondisi 'Good', tambah stok buku
            if ($status === 'Good') {
                $db->query("UPDATE books SET books_stock_quantity = books_stock_quantity + 1 WHERE book_id = ?", [$bookId]);
            }
        }

        $db->transComplete();

        // Cek apakah transaksi berhasil
        if ($db->transStatus() === false) {
            return $this->respondWithError("Gagal memproses pengembalian buku.", null, 500);
        }

        return $this->respondWithSuccess("Buku berhasil dikembalikan.", [
            "loan_id" => $loanId,
            "member_info" => [
                "institution" => $loan->member_institution,
                "full_name" => $loan->member_full_name,
                "barcode" => $loan->member_barcode
            ],
            "punishment_info" => [
                "total_punishment_days" => $totalPunishmentDays,
                "punishment_per_late_day" => (int) $punishmentDaysPerLateDay
            ]
        ]);
    }



    public function get_all_borrow()
    {
        $db = Database::connect();

        $tokenValidation = $this->validateToken('superadmin,frontliner');
        if ($tokenValidation !== true) {
            return $this->respond($tokenValidation, $tokenValidation['status']);
        }

        // Ambil parameter dari query string
        $limit = $this->request->getVar('limit') ?? 10; // Default limit
        $page = $this->request->getVar('page') ?? 1; // Default page
        $search = $this->request->getVar('search');
        $sort = $this->request->getVar('sort');
        $filters = $this->request->getVar('filter') ?? []; // Get all filters
        $enablePagination = $this->request->getVar('pagination') !== 'false'; // Enable pagination by default

        // Hitung offset untuk pagination
        $offset = ($page - 1) * $limit;

        // Start building the query
        $query = "SELECT loan_id, loan_member_id, loan_transaction_code, loan_date, loan_member_institution, loan_member_email, loan_member_full_name, loan_member_address FROM loan";
        $conditions = [];
        $params = [];

        // Handle search condition across all fields
        if ($search) {
            $conditions[] = "(loan_member_institution LIKE ? OR loan_member_full_name LIKE ? OR loan_member_email LIKE ? OR loan_transaction_code LIKE ? OR loan_member_address LIKE ?)";
            $searchParam = '%' . $search . '%'; // Prepare search parameter
            $params = array_fill(0, 5, $searchParam); // Fill params array for each searchable column
        }

        // Define the mapping of filter keys to database columns
        $filterMapping = [
            'member_id' => 'loan_member_id',
            'transaction_code' => 'loan_transaction_code',
            'institution' => 'loan_member_institution',
            'full_name' => 'loan_member_full_name',
            'email' => 'loan_member_email',
            'address' => 'loan_member_address',
        ];


        if (!empty($sort)) {
            $sortField = ltrim($sort, '-');
            $sortDirection = $sort[0] === '-' ? 'DESC' : 'ASC';
            if (array_key_exists($sortField, $filterMapping)) {
                $query .= " ORDER BY {$filterMapping[$sortField]} $sortDirection";
            }
        }


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

        // Add limit and offset for pagination if enabled
        if ($enablePagination) {
            $query .= " LIMIT ? OFFSET ?";
            $params[] = (int) $limit;
            $params[] = (int) $offset;
        }

        try {
            // Execute query to get loan data
            $loanData = $db->query($query, $params)->getResultArray();

            // Format result
            $result = [];
            foreach ($loanData as $loan) {
                $result[] = [
                    'loan_id' => (int) $loan['loan_id'],
                    'member_id' => $loan['loan_member_id'],
                    'transaction_code' => $loan['loan_transaction_code'],
                    'loan_date' => $loan['loan_date'],
                    'institution' => $loan['loan_member_institution'],
                    'email' => $loan['loan_member_email'],
                    'full_name' => $loan['loan_member_full_name'],
                    'address' => $loan['loan_member_address'],
                ];
            }

            // Count total loans for pagination if enabled
            $pagination = [];
            if ($enablePagination) {
                $totalQuery = "SELECT COUNT(*) as total FROM loan";
                if (count($conditions) > 0) {
                    $totalQuery .= ' WHERE ' . implode(' AND ', $conditions);
                }
                $total = $db->query($totalQuery, array_slice($params, 0, count($params) - 2))->getRow()->total;

                // Calculate total pages and pagination details
                $totalPages = ceil($total / $limit);
                $prev = ($page > 1) ? $page - 1 : null;
                $next = ($page < $totalPages) ? $page + 1 : null;
                $start = ($page - 1) * $limit + 1;
                $end = min($page * $limit, $total);
                $detail = range(max(1, $page - 2), min($totalPages, $page + 2));

                // Prepare pagination details
                $pagination = [
                    'total_data' => (int) $total,
                    'total_pages' => (int) $totalPages,
                    'prev' => $prev,
                    'page' => (int) $page,
                    'next' => $next,
                    'detail' => $detail,
                    'start' => $start,
                    'end' => $end,
                ];
            }

            // Return response
            return $this->respondWithSuccess('Loans retrieved successfully.', [
                'data' => $result,
                'pagination' => $pagination
            ]);
        } catch (\Exception $e) {
            return $this->respondWithError('Failed to retrieve loans: ' . $e->getMessage());
        }
    }



    public function get_detail_loan()
    {
        $db = Database::connect();

        $tokenValidation = $this->validateToken('superadmin,frontliner'); // Fungsi helper dipanggil

        if ($tokenValidation !== true) {
            return $this->respond($tokenValidation, $tokenValidation['status']);
        }
        // Ambil parameter id dari query string (bisa loan_id atau book_id)
        $loanId = $this->request->getVar('loan_id');
        $bookId = $this->request->getVar('book_id');

        // Cek apakah salah satu parameter diberikan
        if (empty($loanId) && empty($bookId)) {
            return $this->respondWithValidationError('loan_id or book_id is required.');
        }

        // Mulai membangun query untuk mendapatkan data dari tabel loan dan loan_detail
        $query = "
        SELECT
            loan.loan_id,
            loan.loan_member_id,
            loan.loan_transaction_code,
            loan.loan_date,
            loan.loan_member_institution,
            loan.loan_member_email,
            loan.loan_member_full_name,
            loan.loan_member_address,
            loan_detail.loan_detail_book_id,
            loan_detail.loan_detail_book_title,
            loan_detail.loan_detail_book_publisher_name,
            loan_detail.loan_detail_book_publisher_address,
            loan_detail.loan_detail_book_publisher_phone,
            loan_detail.loan_detail_book_publisher_email,
            loan_detail.loan_detail_book_publication_year,
            loan_detail.loan_detail_book_isbn,
            loan_detail.loan_detail_book_author_name,
            loan_detail.loan_detail_book_author_biography,
            loan_detail.loan_detail_status,
            loan_detail.loan_detail_borrow_date,
            loan_detail.loan_detail_return_date,
            loan_detail.loan_detail_period,
            loan_detail.loan_detail_loan_transaction_code
        FROM loan
        JOIN loan_detail ON loan.loan_transaction_code = loan_detail.loan_detail_loan_transaction_code
    ";

        // Kondisi jika loan_id atau book_id diberikan
        $conditions = [];
        $params = [];

        if (!empty($loanId)) {
            $conditions[] = "loan.loan_id = ?";
            $params[] = $loanId;
        }

        if (!empty($bookId)) {
            $conditions[] = "loan_detail.loan_detail_book_id = ?";
            $params[] = $bookId;
        }

        // Tambahkan kondisi ke query
        if (count($conditions) > 0) {
            $query .= ' WHERE ' . implode(' AND ', $conditions);
        }

        try {
            // Eksekusi query dan ambil hasilnya
            $loanDetail = $db->query($query, $params)->getResultArray();


            $data = [

            ];


            if (empty($loanDetail)) {
                return $this->respondWithSuccess('Data tidak tersedia.', $data);
            }

            // Format data yang akan dikembalikan
            $result = [];
            foreach ($loanDetail as $detail) {
                $result[] = [
                    'loan_id' => (int) $detail['loan_id'],
                    'member_id' => (int) $detail['loan_member_id'],
                    'transaction_code' => $detail['loan_transaction_code'],
                    'loan_date' => $detail['loan_date'],
                    'institution' => $detail['loan_member_institution'],
                    'email' => $detail['loan_member_email'],
                    'full_name' => $detail['loan_member_full_name'],
                    'address' => $detail['loan_member_address'],
                    'book' => [
                        'book_id' => (int) $detail['loan_detail_book_id'],
                        'title' => $detail['loan_detail_book_title'],
                        'publisher_name' => $detail['loan_detail_book_publisher_name'],
                        'publisher_address' => $detail['loan_detail_book_publisher_address'],
                        'publisher_phone' => $detail['loan_detail_book_publisher_phone'],
                        'publisher_email' => $detail['loan_detail_book_publisher_email'],
                        'publication_year' => $detail['loan_detail_book_publication_year'],
                        'isbn' => $detail['loan_detail_book_isbn'],
                        'author_name' => $detail['loan_detail_book_author_name'],
                        'author_biography' => $detail['loan_detail_book_author_biography'],
                    ],
                    'loan_status' => $detail['loan_detail_status'],
                    'borrow_date' => $detail['loan_detail_borrow_date'],
                    'return_date' => $detail['loan_detail_return_date'],
                    'loan_period' => $detail['loan_detail_period'],
                ];
            }

            // Kembalikan respons sukses
            return $this->respondWithSuccess('Loan details retrieved successfully.', [
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return $this->respondWithError('Failed to retrieve loan details: ' . $e->getMessage());
        }
    }


    public function detailed_member_activity()
    {
        $db = Database::connect();

        $tokenValidation = $this->validateToken('superadmin,frontliner'); // Fungsi helper dipanggil

        if ($tokenValidation !== true) {
            return $this->respond($tokenValidation, $tokenValidation['status']);
        }

        $query = "
            SELECT loan_member_id, COUNT(*) as activity_count
            FROM loan
            GROUP BY loan_member_id
            ORDER BY activity_count DESC
            LIMIT 10";
        $members = $db->query($query)->getResultArray();

        $detailedMembers = [];
        foreach ($members as $member) {
            $memberId = $member['loan_member_id'];

            $memberQuery = "SELECT * FROM member WHERE member_id = ?";
            $memberDetails = $db->query($memberQuery, [$memberId])->getRowArray();

            if ($memberDetails) {
                $detailedMembers[] = [
                    'member_id' => $memberDetails['member_id'],
                    'institution' => $memberDetails['member_institution'],
                    'email' => $memberDetails['member_email'],
                    'full_name' => $memberDetails['member_full_name'],
                    'address' => $memberDetails['member_address'],
                    'activity_count' => $member['activity_count'],
                ];
            }
        }

        return $this->respondWithSuccess('Detailed member activity retrieved.', ['data' => $detailedMembers]);
    }

    public function detailed_borrowed_books()
    {
        $db = Database::connect();

        $tokenValidation = $this->validateToken('superadmin,frontliner'); // Fungsi helper dipanggil

        if ($tokenValidation !== true) {
            return $this->respond($tokenValidation, $tokenValidation['status']);
        }

        $query = "
            SELECT loan_detail_book_id as book_id, loan_detail_book_title as book_title,
                   loan_detail_borrow_date as borrow_date, loan_detail_return_date as return_date,
                   loan_detail_status as status, loan_detail_loan_transaction_code
            FROM loan_detail";
        $borrowedBooks = $db->query($query)->getResultArray();

        $detailedBooks = [];
        foreach ($borrowedBooks as $borrowedBook) {
            $bookId = $borrowedBook['book_id'];

            $bookQuery = "SELECT * FROM books WHERE book_id = ?";
            $bookDetails = $db->query($bookQuery, [$bookId])->getRowArray();

            if ($bookDetails) {
                $detailedBooks[] = [
                    'book_id' => $bookDetails['book_id'],
                    'book_title' => $borrowedBook['book_title'],
                    'borrow_date' => $borrowedBook['borrow_date'],
                    'return_date' => $borrowedBook['return_date'],
                    'status' => $borrowedBook['status'],
                    'transaction_code' => $borrowedBook['loan_detail_loan_transaction_code'],
                    'publisher_name' => $bookDetails['books_publisher_id'], // Assuming this maps to publisher table
                    'publication_year' => $bookDetails['books_publication_year'],
                    'isbn' => $bookDetails['books_isbn'],
                    'price' => $bookDetails['books_price'],
                ];
            }
        }

        return $this->respondWithSuccess('Detailed borrowed books retrieved.', ['data' => $detailedBooks]);
    }



}
