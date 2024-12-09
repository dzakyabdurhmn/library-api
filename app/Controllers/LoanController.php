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

            $tokenValidation = $this->validateToken("frontliner,superadmin");

            if ($tokenValidation !== true) {
                return $this->respond(
                    $tokenValidation,
                    $tokenValidation["status"]
                );
            }

            if (
                empty($request["member_id"]) ||
                empty($request["borrow_book"])
            ) {
                return $this->respondWithError(
                    "Member ID dan daftar buku diperlukan.",
                    null,
                    400
                );
            }

            $memberId = $request["member_id"];
            $borrowBooks = $request["borrow_book"];

            $db = Database::connect();

            // Validasi durasi peminjaman
            foreach ($borrowBooks as $book) {
                if (
                    !isset($book["duration"]) ||
                    $book["duration"] <= 0 ||
                    $book["duration"] > 30
                ) {
                    return $this->respondWithError(
                        "Durasi peminjaman harus antara 1-30 hari.",
                        null,
                        400
                    );
                }
            }

            // Ambil detail member
            $memberQuery = $db->query(
                "SELECT member_institution, member_email, member_full_name, member_address FROM member WHERE member_id = ?",
                [$memberId]
            );
            $member = $memberQuery->getRow();

            if (!$member) {
                return $this->respondWithError(
                    "Member tidak ditemukan.",
                    null,
                    404
                );
            }

            // Cek total buku yang sedang dipinjam
            $currentLoansQuery = $db->query(
                "
        SELECT COUNT(*) as total 
        FROM loan_detail ld
        JOIN loan l ON l.loan_id = ld.loan_detail_loan_id
        WHERE ld.loan_detail_status = 'Borrowed' 
        AND l.loan_member_id = ?",
                [$memberId]
            );

            $currentLoans = $currentLoansQuery->getRow()->total;

            if ($currentLoans >= 3) {
                return $this->respondWithError(
                    "Tidak dapat meminjam lebih dari 3 buku dalam satu waktu",
                    null,
                    400
                );
            }

            $booksToBorrow = array_filter($borrowBooks, function ($book) {
                return isset($book["book_id"]) && isset($book["duration"]);
            });

            if (count($booksToBorrow) + $currentLoans > 3) {
                return $this->respondWithError(
                    "Hanya dapat meminjam " .
                    (3 - $currentLoans) .
                    " buku lagi.",
                    null,
                    400
                );
            }

            $db->transBegin();

            try {
                // Insert ke tabel loan
                $loanData = [
                    "loan_member_id" => $memberId,
                    "loan_date" => date("Y-m-d H:i:s"),
                    "loan_member_institution" => $member->member_institution,
                    "loan_member_email" => $member->member_email,
                    "loan_member_full_name" => $member->member_full_name,
                    "loan_member_address" => $member->member_address,
                ];

                $db->table("loan")->insert($loanData);
                $loanId = $db->insertID();

                // Generate unique transaction code
                $transactionCode =
                    "LN-" .
                    date("Ymd") .
                    "-" .
                    str_pad($loanId, 5, "0", STR_PAD_LEFT);

                // Update loan with transaction code
                $db->table("loan")
                    ->where("loan_id", $loanId)
                    ->update(["loan_transaction_code" => $transactionCode]);

                $borrowedBooksDetails = [];

                foreach ($booksToBorrow as $book) {
                    $bookId = $book["book_id"];
                    $duration = $book["duration"];

                    // Cek ketersediaan buku
                    $bookQuery = $db->query(
                        "
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
                        throw new \Exception(
                            "Buku dengan ID {$bookId} tidak ditemukan."
                        );
                    }

                    if ($bookData->books_stock_quantity <= 0) {
                        throw new \Exception(
                            "Stok buku '{$bookData->books_title}' tidak tersedia."
                        );
                    }

                    // Hitung tanggal pengembalian
                    $returnDate = date(
                        "Y-m-d H:i:s",
                        strtotime("+{$duration} days")
                    );

                    // Insert loan detail
                    $loanDetailData = [
                        "loan_detail_loan_id" => $loanId,
                        "loan_detail_book_id" => $bookId,
                        "loan_detail_borrow_date" => date("Y-m-d H:i:s"),
                        "loan_detail_status" => "Borrowed",
                        "loan_detail_book_title" => $bookData->books_title,
                        "loan_detail_book_isbn" => $bookData->books_isbn,
                        "loan_detail_book_publication_year" =>
                            $bookData->books_publication_year,
                        "loan_detail_book_author_name" =>
                            $bookData->author_name,
                        "loan_detail_book_author_biography" =>
                            $bookData->author_biography,
                        "loan_detail_book_publisher_name" =>
                            $bookData->publisher_name,
                        "loan_detail_book_publisher_address" =>
                            $bookData->publisher_address,
                        "loan_detail_book_publisher_phone" =>
                            $bookData->publisher_phone,
                        "loan_detail_book_publisher_email" =>
                            $bookData->publisher_email,
                        "loan_detail_book_publisher_id" =>
                            $bookData->books_publisher_id,
                        "loan_detail_book_author_id" =>
                            $bookData->books_author_id,
                        "loan_detail_loan_duration" => $duration,
                        "loan_detail_expected_return_date" => $returnDate,
                        "loan_detail_loan_transaction_code" => $transactionCode,
                    ];

                    try {
                        // Siapkan query insert
                        $query = $db->query(
                            "
    INSERT INTO loan_detail (
        loan_detail_loan_id,
        loan_detail_book_id,
        loan_detail_book_title,
        loan_detail_book_publisher_name,
        loan_detail_book_publisher_address,
        loan_detail_book_publisher_phone,
        loan_detail_book_publisher_email,
        loan_detail_book_publication_year,
        loan_detail_book_isbn,
        loan_detail_book_author_name,
        loan_detail_book_author_biography,
        loan_detail_status,
        loan_detail_borrow_date,
        loan_detail_return_date,
        loan_detail_period,
        loan_detail_loan_transaction_code,
        loan_detail_book_publisher_id,
        loan_detail_book_author_id,
        loan_detail_loan_duration
    ) VALUES (
        ?, ?, ?, ?, ?, 
        ?, ?, ?, ?, ?, 
        ?, ?, ?, ?, ?, 
        ?, ?, ?, ?
    )
",
                            [
                                $loanId,
                                $bookId,
                                $bookData->books_title,
                                $bookData->publisher_name,
                                $bookData->publisher_address,
                                $bookData->publisher_phone,
                                $bookData->publisher_email,
                                $bookData->books_publication_year,
                                $bookData->books_isbn,
                                $bookData->author_name,
                                $bookData->author_biography,
                                "Borrowed",
                                date("Y-m-d H:i:s"),
                                null, // return_date
                                $duration, // period
                                $transactionCode,
                                $bookData->books_publisher_id,
                                $bookData->books_author_id,
                                $duration,
                            ]
                        );

                        // Cek apakah query berhasil dieksekusi
                        if (!$query) {
                            // Tangkap error database
                            $error = $db->error();
                            throw new \Exception(
                                "Gagal insert loan detail: " . $error["message"]
                            );
                        }
                        // Optional: Add debug logging
                        log_message(
                            "error",
                            "Loan Detail Insert Data: " .
                            json_encode($loanDetailData)
                        );
                    } catch (\Exception $e) {
                        log_message(
                            "error",
                            "Loan Detail Insert Error: " . $e->getMessage()
                        );
                        throw $e;
                    }

                    // Update stok buku
                    $db->query(
                        "
                UPDATE books 
                SET books_stock_quantity = books_stock_quantity - 1 
                WHERE book_id = ? AND books_stock_quantity > 0",
                        [$bookId]
                    );

                    if ($db->affectedRows() === 0) {
                        throw new \Exception(
                            "Gagal mengupdate stok buku '{$bookData->books_title}'."
                        );
                    }

                    // Simpan detail buku yang dipinjam
                    $borrowedBooksDetails[] = [
                        "book_id" => $bookId,
                        "title" => $bookData->books_title,
                        "duration" => $duration,
                    ];
                }

                $db->transCommit();

                return $this->respondWithSuccess("Berhasil meminjam buku", [
                    "data" => [
                        "loan_id" => $loanId,
                        "transaction_code" => $transactionCode,
                        "borrowed_books" => $borrowedBooksDetails,
                    ],
                ]);
            } catch (\Exception $e) {
                $db->transRollback();
                return $this->respondWithError($e->getMessage(), null, 500);
            }
        } catch (\Throwable $e) {
            return $this->respondWithError(
                "Terjadi kesalahan: " . $e->getMessage(),
                null,
                500
            );
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
    SELECT l.loan_id, l.loan_member_id, l.loan_transaction_code,
           m.member_institution, m.member_email, m.member_full_name, m.member_barcode, 
           m.member_address
    FROM loan l
    JOIN member m ON m.member_id = l.loan_member_id
    WHERE l.loan_id = ?",
            [$loanId]
        );

        $loan = $loanQuery->getRow();

        if (!$loan) {
            return $this->respondWithError("Data peminjaman tidak ditemukan.", null, 404);
        }

        $validStatuses = ['Good', 'Borrowed', 'Broken', 'Missing'];
        $totalPunishmentAmount = 0;
        $totalLateDays = 0; // Tambahkan variabel untuk total late days

        // Mulai transaksi manual
        $db->query("START TRANSACTION");

        try {
            foreach ($returnBooks as $book) {
                $bookId = $book['book_id'];
                $status = $book['status'];

                if (!in_array($status, $validStatuses)) {
                    $db->query("ROLLBACK");
                    return $this->respondWithError(
                        "Status '$status' tidak valid. Status yang diperbolehkan: " . implode(', ', $validStatuses),
                        null,
                        400
                    );
                }

                // Cek buku yang dipinjam
                $loanDetailQuery = $db->query("
            SELECT * 
            FROM loan_detail 
            WHERE loan_detail_loan_id = ? 
            AND loan_detail_book_id = ? 
            AND loan_detail_status = 'Borrowed'",
                    [$loanId, $bookId]
                );

                $loanDetail = $loanDetailQuery->getRow();

                if (!$loanDetail) {
                    $db->query("ROLLBACK");
                    return $this->respondWithError("Buku ini tidak tercatat dalam peminjaman tersebut.", null, 400);
                }

                // Ambil detail buku
                $bookQuery = $db->query("
            SELECT b.*, p.publisher_id, a.author_id
            FROM books b
            LEFT JOIN publisher p ON b.books_publisher_id = p.publisher_id
            LEFT JOIN author a ON b.books_author_id = a.author_id
            WHERE book_id = ?",
                    [$bookId]
                );
                $bookDetail = $bookQuery->getRow();

                // Hitung durasi peminjaman
                $borrowDateTime = new \DateTime($loanDetail->loan_detail_borrow_date);
                $returnDateTime = new \DateTime();
                $interval = $borrowDateTime->diff($returnDateTime);
                $loanPeriod = $interval->days;

                // Hitung hari keterlambatan
                $maxLoanDays = $loanDetail->loan_detail_loan_duration;
                $lateDays = max(0, $loanPeriod - $maxLoanDays);
                $totalLateDays += $lateDays; // Akumulasi total late days

                // Update loan_detail dengan status baru
                $db->query("
            UPDATE loan_detail 
            SET loan_detail_status = ?, 
                loan_detail_return_date = NOW(),
                loan_detail_period = ?
            WHERE loan_detail_loan_id = ? 
            AND loan_detail_book_id = ?
        ", [
                    $status,
                    $loanPeriod,
                    $loanId,
                    $bookId
                ]);

                // Tambah stok buku jika kondisi baik
                if ($status === 'Good') {
                    $db->query("
                UPDATE books 
                SET books_stock_quantity = books_stock_quantity + 1 
                WHERE book_id = ?
            ", [$bookId]);
                }

                // Hitung denda jika terlambat atau rusak/hilang
                if ($lateDays > 0 || $status !== 'Good') {
                    // Ambil persentase denda
                    $fineQuery = $db->query("
                SELECT percentage_object 
                FROM percentage 
                WHERE percentage_name = CASE 
                    WHEN ? = 'Broken' THEN 'broken_book_fine'
                    WHEN ? = 'Missing' THEN 'missing_book_fine'
                    ELSE 'keterlambatan_pengembalian'
                END
                ORDER BY punishment_created_at DESC 
                LIMIT 1
            ", [$status, $status]);

                    $fineData = $fineQuery->getRow();
                    $fineFeePercent = $fineData ?
                        json_decode($fineData->percentage_object)->fine_fee_in_percent ??
                        json_decode($fineData->percentage_object)->day ?? 0 : 0;

                    // Hitung total denda
                    $punishmentAmount = $status === 'Good' ? 0 :
                        ($bookDetail->books_price * ($fineFeePercent / 100));

                    $totalPunishmentAmount += $punishmentAmount;

                    // Ambil informasi member tambahan
                    $memberQuery = $db->query("
                SELECT member_job, member_status, member_religion, member_gender 
                FROM member 
                WHERE member_id = ?",
                        [$loan->loan_member_id]
                    );
                    $memberData = $memberQuery->getRow();

                    // Insert punishment
                    $db->query("
                INSERT INTO punishment (
                    member_id, member_institution, member_email, 
                    member_full_name, member_address, 
                    member_job, member_status, member_religion, 
                    member_barcode, member_gender,
                    book_id, books_publisher_id, books_author_id, 
                    books_title, books_publication_year, books_isbn, 
                    books_stock_quantity, books_price, books_barcode, 
                    punishment_type, punishment_amount, 
                    punishment_late_days
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ", [
                        $loan->loan_member_id,
                        $loan->member_institution,
                        $loan->member_email,
                        $loan->member_full_name,
                        $loan->member_address,
                        $memberData->member_job ?? null,
                        $memberData->member_status ?? null,
                        $memberData->member_religion ?? null,
                        $loan->member_barcode,
                        $memberData->member_gender ?? null,
                        $bookId,
                        $bookDetail->publisher_id ?? null,
                        $bookDetail->author_id ?? null,
                        $bookDetail->books_title,
                        $bookDetail->books_publication_year,
                        $bookDetail->books_isbn,
                        $bookDetail->books_stock_quantity,
                        $bookDetail->books_price,
                        $bookDetail->books_barcode,
                        $status !== 'Good' ? $status : 'Late Return',
                        $punishmentAmount,
                        $lateDays // Simpan jumlah hari keterlambatan
                    ]);
                }
            }

            // Commit transaksi jika semua berhasil
            $db->query("COMMIT");

            return $this->respondWithSuccess("Buku berhasil dikembalikan.", [
                "loan_id" => $loanId,
                "loan_transaction_code" => $loan->loan_transaction_code,
                "member_info" => [
                    "institution" => $loan->member_institution,
                    "full_name" => $loan->member_full_name,
                    "barcode" => $loan->member_barcode
                ],
                "punishment_info" => [
                    "total_punishment_amount" => "IDR " . number_format($totalPunishmentAmount, 0, ',', '.'),
                    "late_days" => $totalLateDays // Total late days
                ]
            ]);
        } catch (\Exception $e) {
            $db->query("ROLLBACK");
            return $this->respondWithError("Terjadi kesalahan: " . $e->getMessage(), null, 500);
        }
    }

    public function get_all_borrow()
    {
        $db = Database::connect();

        $tokenValidation = $this->validateToken("superadmin,frontliner");
        if ($tokenValidation !== true) {
            return $this->respond($tokenValidation, $tokenValidation["status"]);
        }

        // Ambil parameter dari query string
        $limit = $this->request->getVar("limit") ?? 10; // Default limit
        $page = $this->request->getVar("page") ?? 1; // Default page
        $search = $this->request->getVar("search");
        $sort = $this->request->getVar("sort");
        $filters = $this->request->getVar("filter") ?? []; // Get all filters
        $enablePagination = $this->request->getVar("pagination") !== "false"; // Enable pagination by default

        // Hitung offset untuk pagination
        $offset = ($page - 1) * $limit;

        // Start building the query
        $query =
            "SELECT loan_id, loan_member_id, loan_transaction_code, loan_date, loan_member_institution, loan_member_email, loan_member_full_name, loan_member_address FROM loan";
        $conditions = [];
        $params = [];

        // Handle search condition across all fields
        if ($search) {
            $conditions[] =
                "(loan_member_institution LIKE ? OR loan_member_full_name LIKE ? OR loan_member_email LIKE ? OR loan_transaction_code LIKE ? OR loan_member_address LIKE ?)";
            $searchParam = "%" . $search . "%"; // Prepare search parameter
            $params = array_fill(0, 5, $searchParam); // Fill params array for each searchable column
        }

        // Define the mapping of filter keys to database columns
        $filterMapping = [
            "id" => "loan_id",
            "member_id" => "loan_member_id",
            "transaction_code" => "loan_transaction_code",
            "institution" => "loan_member_institution",
            "full_name" => "loan_member_full_name",
            "email" => "loan_member_email",
            "address" => "loan_member_address",
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
            $query .= " WHERE " . implode(" AND ", $conditions);
        }

        // Handle sorting
        if (!empty($sort)) {
            $sortField = ltrim($sort, "-");
            $sortDirection = $sort[0] === "-" ? "DESC" : "ASC";
            if (array_key_exists($sortField, $filterMapping)) {
                $query .= " ORDER BY {$filterMapping[$sortField]} $sortDirection";
            }
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

            // Jika tidak ada data dan filter 'id' digunakan, kembalikan respons khusus
            if (empty($loanData) && isset($filters["id"])) {
                return $this->respondWithError(
                    "Data peminjaman dengan ID tersebut tidak ditemukan.",
                    null,
                    404
                );
            }

            // Format result
            $result = [];
            foreach ($loanData as $loan) {
                $result[] = [
                    "loan_id" => (int) $loan["loan_id"],
                    "member_id" => $loan["loan_member_id"],
                    "transaction_code" => $loan["loan_transaction_code"],
                    "loan_date" => $loan["loan_date"],
                    "institution" => $loan["loan_member_institution"],
                    "email" => $loan["loan_member_email"],
                    "full_name" => $loan["loan_member_full_name"],
                    "address" => $loan["loan_member_address"],
                ];
            }

            // Count total loans for pagination if enabled
            $pagination = [];
            if ($enablePagination) {
                $totalQuery = "SELECT COUNT(*) as total FROM loan";
                if (count($conditions) > 0) {
                    $totalQuery .= " WHERE " . implode(" AND ", $conditions);
                }
                $total = $db
                    ->query(
                        $totalQuery,
                        array_slice($params, 0, count($params) - 2)
                    )
                    ->getRow()->total;

                // Calculate total pages and pagination details
                $totalPages = ceil($total / $limit);
                $prev = $page > 1 ? $page - 1 : null;
                $next = $page < $totalPages ? $page + 1 : null;
                $start = ($page - 1) * $limit + 1;
                $end = min($page * $limit, $total);
                $detail = range(max(1, $page - 2), min($totalPages, $page + 2));

                // Prepare pagination details
                $pagination = [
                    "total_data" => (int) $total,
                    "total_pages" => (int) $totalPages,
                    "prev" => $prev,
                    "page" => (int) $page,
                    "next" => $next,
                    "detail" => $detail,
                    "start" => $start,
                    "end" => $end,
                ];
            }

            // Return response
            return $this->respondWithSuccess("Loans retrieved successfully.", [
                "data" => $result,
                "pagination" => $pagination,
            ]);
        } catch (\Exception $e) {
            return $this->respondWithError(
                "Failed to retrieve loans: " . $e->getMessage()
            );
        }
    }


    public function get_detail_loan()
    {
        $db = Database::connect();

        $tokenValidation = $this->validateToken("superadmin,frontliner");

        if ($tokenValidation !== true) {
            return $this->respond($tokenValidation, $tokenValidation["status"]);
        }

        // Ambil parameter id dari query string (bisa loan_id atau book_id)
        $loanId = $this->request->getVar("loan_id");
        $bookId = $this->request->getVar("book_id");

        // Cek apakah salah satu parameter diberikan
        if (empty($loanId) && empty($bookId)) {
            return $this->respondWithValidationError(
                "loan_id or book_id is required."
            );
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
    JOIN loan_detail ON loan.loan_id = loan_detail.loan_detail_loan_id
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
            $query .= " WHERE " . implode(" AND ", $conditions);
        }

        try {
            // Eksekusi query dan ambil hasilnya
            $loanDetail = $db->query($query, $params)->getResultArray();

            if (empty($loanDetail)) {
                return $this->respondWithSuccess("Data tidak tersedia.", []);
            }

            // Ambil data loan utama dari baris pertama
            $mainLoan = $loanDetail[0];

            // Format buku-buku dalam loan
            $bookArray = [];
            foreach ($loanDetail as $detail) {
                $bookArray[] = [
                    "book_id" => (int) $detail["loan_detail_book_id"],
                    "title" => $detail["loan_detail_book_title"],
                    "publisher_name" =>
                        $detail["loan_detail_book_publisher_name"],
                    "publisher_address" =>
                        $detail["loan_detail_book_publisher_address"],
                    "publisher_phone" =>
                        $detail["loan_detail_book_publisher_phone"],
                    "publisher_email" =>
                        $detail["loan_detail_book_publisher_email"],
                    "publication_year" =>
                        $detail["loan_detail_book_publication_year"],
                    "isbn" => $detail["loan_detail_book_isbn"],
                    "author_name" => $detail["loan_detail_book_author_name"],
                    "author_biography" =>
                        $detail["loan_detail_book_author_biography"],
                ];
            }

            // Struktur respons yang diinginkan
            $result = [
                "loan_id" => (int) $mainLoan["loan_id"],
                "member_id" => (int) $mainLoan["loan_member_id"],
                "transaction_code" => $mainLoan["loan_transaction_code"],
                "loan_date" => $mainLoan["loan_date"],
                "institution" => $mainLoan["loan_member_institution"],
                "email" => $mainLoan["loan_member_email"],
                "full_name" => $mainLoan["loan_member_full_name"],
                "address" => $mainLoan["loan_member_address"],
                "book_array" => $bookArray,
            ];

            // Kembalikan respons sukses
            return $this->respondWithSuccess(
                "Loan details retrieved successfully.",
                $result
            );
        } catch (\Exception $e) {
            return $this->respondWithError(
                "Failed to retrieve loan details: " . $e->getMessage()
            );
        }
    }

    public function detailed_member_activity()
    {
        $db = Database::connect();

        $tokenValidation = $this->validateToken("superadmin,frontliner"); // Fungsi helper dipanggil

        if ($tokenValidation !== true) {
            return $this->respond($tokenValidation, $tokenValidation["status"]);
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
            $memberId = $member["loan_member_id"];

            $memberQuery = "SELECT * FROM member WHERE member_id = ?";
            $memberDetails = $db
                ->query($memberQuery, [$memberId])
                ->getRowArray();

            if ($memberDetails) {
                $detailedMembers[] = [
                    "member_id" => $memberDetails["member_id"],
                    "institution" => $memberDetails["member_institution"],
                    "email" => $memberDetails["member_email"],
                    "full_name" => $memberDetails["member_full_name"],
                    "address" => $memberDetails["member_address"],
                    "activity_count" => $member["activity_count"],
                ];
            }
        }

        return $this->respondWithSuccess(
            "Detailed member activity retrieved.",
            ["data" => $detailedMembers]
        );
    }

    public function detailed_borrowed_books()
    {
        $db = Database::connect();

        $tokenValidation = $this->validateToken("superadmin,frontliner"); // Fungsi helper dipanggil

        if ($tokenValidation !== true) {
            return $this->respond($tokenValidation, $tokenValidation["status"]);
        }

        $query = "
            SELECT loan_detail_book_id as book_id, loan_detail_book_title as book_title,
                   loan_detail_borrow_date as borrow_date, loan_detail_return_date as return_date,
                   loan_detail_status as status, loan_detail_loan_transaction_code
            FROM loan_detail";
        $borrowedBooks = $db->query($query)->getResultArray();

        $detailedBooks = [];
        foreach ($borrowedBooks as $borrowedBook) {
            $bookId = $borrowedBook["book_id"];

            $bookQuery = "SELECT * FROM books WHERE book_id = ?";
            $bookDetails = $db->query($bookQuery, [$bookId])->getRowArray();

            if ($bookDetails) {
                $detailedBooks[] = [
                    "book_id" => $bookDetails["book_id"],
                    "book_title" => $borrowedBook["book_title"],
                    "borrow_date" => $borrowedBook["borrow_date"],
                    "return_date" => $borrowedBook["return_date"],
                    "status" => $borrowedBook["status"],
                    "transaction_code" =>
                        $borrowedBook["loan_detail_loan_transaction_code"],
                    "publisher_name" => $bookDetails["books_publisher_id"], // Assuming this maps to publisher table
                    "publication_year" =>
                        $bookDetails["books_publication_year"],
                    "isbn" => $bookDetails["books_isbn"],
                    "price" => $bookDetails["books_price"],
                ];
            }
        }

        return $this->respondWithSuccess("Detailed borrowed books retrieved.", [
            "data" => $detailedBooks,
        ]);
    }
}
