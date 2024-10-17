<?php

namespace App\Controllers;

use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\RESTful\ResourceController;
use Config\Database;

class LoanController extends AuthorizationController
{


    public function borrow_book()
    {
        $request = $this->request->getJSON(true);

        $tokenValidation = $this->validateToken('frontliner'); // Fungsi helper dipanggil

        if ($tokenValidation !== true) {
            return $this->respond($tokenValidation, $tokenValidation['status']);
        }

        if (empty($request['member_id']) || empty($request['borrow_book'])) {
            return $this->respondWithError("Member buku dan id buku di perlukan.", null, 400);
        }

        $memberId = $request['member_id'];
        $borrowBooks = $request['borrow_book'];

        $db = Database::connect();

        // Ambil detail member untuk diinsert ke tabel loan
        $memberQuery = $db->query("SELECT member_username, member_email, member_full_name, member_address FROM member WHERE member_id = ?", [$memberId]);
        $member = $memberQuery->getRow();

        if (!$member) {
            return $this->respondWithError("Member not found.", null, 404);
        }

        // Cek total buku yang sedang dipinjam oleh member
        $currentLoansQuery = $db->query("
        SELECT COUNT(*) as total 
        FROM loan_detail 
        WHERE loan_detail_status = 'Borrowed' 
        AND loan_detail_loan_transaction_code IN (
            SELECT loan_transaction_code 
            FROM loan 
            WHERE loan_member_id = ?
        )", [$memberId]);
        $currentLoans = $currentLoansQuery->getRow()->total;

        // Periksa apakah sudah mencapai batas
        if ($currentLoans >= 3) {
            return $this->respondWithError("Anda tidak dapat meminjam lebih dari 3 buku dalam satu waktu", null, 400);
        }

        // Batasi jumlah buku yang dapat dipinjam
        $booksToBorrow = array_filter($borrowBooks, function ($book) {
            return isset($book['book_id']);
        });

        // Validasi total buku yang ingin dipinjam
        if (count($booksToBorrow) + $currentLoans > 3) {
            return $this->respondWithError("Anda hanya bisa meminjam " . (3 - $currentLoans) . "buku.", null, 400);
        }

        $db->transStart();

        // Generate transaction code
        $transactionCode = uniqid('loan_');

        // Insert data ke tabel loan beserta detail member
        $db->query("
        INSERT INTO loan (loan_member_id, loan_transaction_code, loan_date, loan_member_username, loan_member_email, loan_member_full_name, loan_member_address) 
        VALUES (?, ?, NOW(), ?, ?, ?, ?)",
            [$memberId, $transactionCode, $member->member_username, $member->member_email, $member->member_full_name, $member->member_address]
        );

        foreach ($booksToBorrow as $book) {
            $bookId = $book['book_id'];

            // Cek stok buku dan ambil detail buku beserta publisher
            $bookQuery = $db->query("
            SELECT b.books_stock_quantity, b.books_title, b.books_isbn, b.books_publication_year, a.author_name, a.author_biography, 
                   p.publisher_name, p.publisher_address, p.publisher_phone, p.publisher_email 
            FROM books b
            LEFT JOIN author a ON b.books_author_id = a.author_id
            LEFT JOIN publisher p ON b.books_publisher_id = p.publisher_id
            WHERE b.book_id = ?", [$bookId]);
            $bookData = $bookQuery->getRow();

            if ($bookData->books_stock_quantity <= 0) {
                $db->transRollback();
                return $this->respondWithError("Book ID $bookId is out of stock.", null, 400);
            }

            // Insert detail peminjaman dengan detail buku dan publisher
            $db->query("
            INSERT INTO loan_detail 
            (loan_detail_book_id, loan_detail_book_title, loan_detail_book_publisher_name, loan_detail_book_publisher_address, 
             loan_detail_book_publisher_phone, loan_detail_book_publisher_email, loan_detail_book_publication_year, 
             loan_detail_book_isbn, loan_detail_book_author_name, loan_detail_book_author_biography, 
             loan_detail_borrow_date, loan_detail_status, loan_detail_period, loan_detail_loan_transaction_code) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'Borrowed', 0, ?)",
                [
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
                    $transactionCode
                ]
            );

            // Kurangi stok buku
            $db->query("UPDATE books SET books_stock_quantity = books_stock_quantity - 1 WHERE book_id = ?", [$bookId]);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->respondWithError("Terdapat kesalahan di sisi server:", null, 500);
        }

        return $this->respondWithSuccess("Behasil meminjam data buku");
    }


    public function deport()
    {
        $db = Database::connect();


        $tokenValidation = $this->validateToken('superadmin'); // Fungsi helper dipanggil

        if ($tokenValidation !== true) {
            return $this->respond($tokenValidation, $tokenValidation['status']);
        }

        $request = $this->request->getJSON(true);

        if (empty($request['member_id']) || empty($request['borrow_book'])) {
            return $this->respondWithValidationError("Member ID and book details are required.");
        }

        $memberId = $request['member_id'];
        $returnBooks = $request['borrow_book'];

        // Ambil detail member
        $memberQuery = $db->query("SELECT member_username, member_email, member_full_name, member_barcode FROM member WHERE member_id = ?", [$memberId]);
        $member = $memberQuery->getRow();

        if (!$member) {
            return $this->respondWithNotFound("Member not found.");
        }

        // Definisikan status yang valid
        $validStatuses = ['Good', 'Borrowed', 'Broken', 'Missing'];

        $db->transStart();

        foreach ($returnBooks as $book) {
            $bookId = $book['book_id'];
            $status = $book['status'];

            // Validasi status
            if (!in_array($status, $validStatuses)) {
                return $this->respondWithValidationError("Invalid status '$status'. Allowed statuses are: " . implode(', ', $validStatuses));
            }

            // Ambil data buku untuk validasi
            $bookQuery = $db->query("SELECT books_title FROM books WHERE book_id = ?", [$bookId]);
            $bookData = $bookQuery->getRow();

            if (!$bookData) {
                return $this->respondWithNotFound("Book with ID $bookId not found.");
            }

            // Cek apakah member telah meminjam buku ini
            $loanDetailQuery = $db->query("
                SELECT loan_detail_borrow_date, loan_detail_loan_transaction_code 
                FROM loan_detail 
                JOIN loan ON loan.loan_transaction_code = loan_detail.loan_detail_loan_transaction_code 
                WHERE loan.loan_member_id = ? AND loan_detail.loan_detail_book_id = ? AND loan_detail.loan_detail_status = 'Borrowed'",
                [$memberId, $bookId]
            );

            $loanDetail = $loanDetailQuery->getRow();

            // Jika member tidak meminjam buku tersebut
            if (!$loanDetail) {
                return $this->respondWithError("Member did not borrow this book.", [
                    "username" => $member->member_username,
                    "email" => $member->member_email,
                    "full_name" => $member->member_full_name,
                    "barcode" => $member->member_barcode,
                    "book_id" => $bookId,
                    "book_title" => $bookData->books_title
                ]);
            }

            // Hitung periode peminjaman dalam hari
            $returnDate = date('Y-m-d H:i:s'); // Tanggal pengembalian saat ini
            $borrowDateTime = new \DateTime($loanDetail->loan_detail_borrow_date);
            $returnDateTime = new \DateTime($returnDate);
            $interval = $borrowDateTime->diff($returnDateTime);
            $loanPeriod = $interval->days; // Periode dalam hari

            // Cek status buku dan lakukan update yang sesuai
            if ($status === 'Broken') {
                $db->query("UPDATE loan_detail 
                            SET loan_detail_status = 'Broken', loan_detail_return_date = NOW(), loan_detail_period = ? 
                            WHERE loan_detail_book_id = ? AND loan_detail_status = 'Borrowed'",
                    [$loanPeriod, $bookId]
                );
            } else if ($status === 'Good') {
                $db->query("UPDATE loan_detail 
                            SET loan_detail_status = 'Returned', loan_detail_return_date = NOW(), loan_detail_period = ? 
                            WHERE loan_detail_book_id = ? AND loan_detail_status = 'Borrowed'",
                    [$loanPeriod, $bookId]
                );

                $db->query("UPDATE books SET books_stock_quantity = books_stock_quantity + 1 WHERE book_id = ?", [$bookId]);
            } else if ($status === 'Missing') {
                $db->query("UPDATE loan_detail 
                            SET loan_detail_status = 'Missing', loan_detail_return_date = NOW(), loan_detail_period = ? 
                            WHERE loan_detail_book_id = ? AND loan_detail_status = 'Borrowed'",
                    [$loanPeriod, $bookId]
                );
            }
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->respondWithDeleted("Failed to return books.");
        }

        return $this->respondWithSuccess("Books returned successfully.", [
            "member_id" => $memberId,
            "username" => $member->member_username,
            "full_name" => $member->member_full_name,
            "barcode" => $member->member_barcode,
            "book_id" => $bookId,
            "book_title" => $bookData->books_title
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
        $filters = $this->request->getVar('filter') ?? []; // Get all filters
        $enablePagination = $this->request->getVar('pagination') !== 'false'; // Enable pagination by default

        // Hitung offset untuk pagination
        $offset = ($page - 1) * $limit;

        // Start building the query
        $query = "SELECT loan_id, loan_member_id, loan_transaction_code, loan_date, loan_member_username, loan_member_email, loan_member_full_name, loan_member_address FROM loan";
        $conditions = [];
        $params = [];

        // Handle search condition across all fields
        if ($search) {
            $conditions[] = "(loan_member_username LIKE ? OR loan_member_full_name LIKE ? OR loan_member_email LIKE ? OR loan_transaction_code LIKE ? OR loan_member_address LIKE ?)";
            $searchParam = '%' . $search . '%'; // Prepare search parameter
            $params = array_fill(0, 5, $searchParam); // Fill params array for each searchable column
        }

        // Define the mapping of filter keys to database columns
        $filterMapping = [
            'member_id' => 'loan_member_id',
            'transaction_code' => 'loan_transaction_code',
            'username' => 'loan_member_username',
            'full_name' => 'loan_member_full_name',
            'email' => 'loan_member_email',
            'address' => 'loan_member_address',
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
                    'username' => $loan['loan_member_username'],
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
            loan.loan_member_username,
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

            if (empty($loanDetail)) {
                return $this->respondWithNotFound('Loan or book not found.');
            }

            // Format data yang akan dikembalikan
            $result = [];
            foreach ($loanDetail as $detail) {
                $result[] = [
                    'loan_id' => (int) $detail['loan_id'],
                    'member_id' => (int) $detail['loan_member_id'],
                    'transaction_code' => $detail['loan_transaction_code'],
                    'loan_date' => $detail['loan_date'],
                    'username' => $detail['loan_member_username'],
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
                    'username' => $memberDetails['member_username'],
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
