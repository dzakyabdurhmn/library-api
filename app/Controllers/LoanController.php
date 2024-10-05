<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use Config\Database;

class LoanController extends CoreController
{


    public function borrow_book()
    {
        $request = $this->request->getJSON(true);

        if (empty($request['member_id']) || empty($request['borrow_book'])) {
            return $this->respondWithError("Member ID and book details are required.", null, 400);
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
            return $this->respondWithError("You cannot borrow more than 3 books at a time.", null, 400);
        }

        // Batasi jumlah buku yang dapat dipinjam
        $booksToBorrow = array_filter($borrowBooks, function ($book) {
            return isset($book['book_id']);
        });

        // Validasi total buku yang ingin dipinjam
        if (count($booksToBorrow) + $currentLoans > 3) {
            return $this->respondWithError("You can only borrow " . (3 - $currentLoans) . " more book(s).", null, 400);
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
            return $this->respondWithError("Failed to borrow books.", null, 500);
        }

        return $this->respondWithSuccess("Books borrowed successfully.");
    }


    public function deport()
    {
        // Inisialisasi database
        $db = \Config\Database::connect();

        $request = $this->request->getJSON(true);

        if (empty($request['member_id']) || empty($request['borrow_book'])) {
            return $this->respond([
                "status" => 400,
                "message" => ["Member ID and book details are required."],
                "result" => null
            ], 400);
        }

        $memberId = $request['member_id'];
        $returnBooks = $request['borrow_book'];

        // Ambil detail member
        $memberQuery = $db->query("SELECT member_username, member_email, member_full_name, member_barcode FROM member WHERE member_id = ?", [$memberId]);
        $member = $memberQuery->getRow();

        if (!$member) {
            return $this->respond([
                "status" => 404,
                "message" => ["Member not found."],
                "result" => null
            ], 404);
        }

        // Definisikan status yang valid
        $validStatuses = ['Good', 'Borrowed', 'Broken', 'Missing'];

        $db->transStart();

        foreach ($returnBooks as $book) {
            $bookId = $book['book_id'];
            $status = $book['status'];

            // Validasi status
            if (!in_array($status, $validStatuses)) {
                return $this->respond([
                    "status" => 400,
                    "message" => ["Invalid status '$status'. Allowed statuses are: " . implode(', ', $validStatuses)],
                    "result" => null
                ], 400);
            }

            // Ambil data buku untuk validasi
            $bookQuery = $db->query("SELECT books_title FROM books WHERE book_id = ?", [$bookId]);
            $bookData = $bookQuery->getRow();

            if (!$bookData) {
                return $this->respond([
                    "status" => 404,
                    "message" => ["Book with ID $bookId not found."],
                    "result" => null
                ], 404);
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
                return $this->respond([
                    "status" => 400,
                    "message" => "Member did not borrow this book.",
                    "result" => [
                        "data" => [
                            "username" => $member->member_username,
                            "email" => $member->member_email,
                            "full_name" => $member->member_full_name,
                            "barcode" => $member->member_barcode,
                            "book_id" => $bookId,
                            "book_title" => $bookData->books_title
                        ]
                    ]
                ], 400);
            }

            // Hitung periode peminjaman dalam hari
            $returnDate = date('Y-m-d H:i:s'); // Tanggal pengembalian saat ini
            $borrowDateTime = new \DateTime($loanDetail->loan_detail_borrow_date);
            $returnDateTime = new \DateTime($returnDate);
            $interval = $borrowDateTime->diff($returnDateTime);
            $loanPeriod = $interval->days; // Periode dalam hari

            // Cek status buku dan lakukan update yang sesuai
            if ($status === 'Broken') {
                // Jika rusak, jangan tambah stok
                $db->query("UPDATE loan_detail 
                        SET loan_detail_status = 'Broken', loan_detail_return_date = NOW(), loan_detail_period = ? 
                        WHERE loan_detail_book_id = ? AND loan_detail_status = 'Borrowed'",
                    [$loanPeriod, $bookId]
                );
            } else if ($status === 'Good') {
                // Jika baik, tambah stok
                $db->query("UPDATE loan_detail 
                        SET loan_detail_status = 'Returned', loan_detail_return_date = NOW(), loan_detail_period = ? 
                        WHERE loan_detail_book_id = ? AND loan_detail_status = 'Borrowed'",
                    [$loanPeriod, $bookId]
                );

                $db->query("UPDATE books SET books_stock_quantity = books_stock_quantity + 1 WHERE book_id = ?", [$bookId]);
            } else if ($status === 'Missing') {
                // Jika buku hilang, set status ke 'Missing' tanpa menambah stok
                $db->query("UPDATE loan_detail 
                        SET loan_detail_status = 'Missing', loan_detail_return_date = NOW(), loan_detail_period = ? 
                        WHERE loan_detail_book_id = ? AND loan_detail_status = 'Borrowed'",
                    [$loanPeriod, $bookId]
                );
            }
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->respond([
                "status" => 500,
                "message" => ["Failed to return books."],
                "result" => null
            ], 500);
        }

        return $this->respond([
            "status" => 200,
            "message" => ["Books returned successfully."],
            "result" => [
                "data" => [
                    "member_id" => $memberId,
                    "username" => $member->member_username,
                    "full_name" => $member->member_full_name,
                    "barcode" => $member->member_barcode,
                    "book_id" => $bookId,
                    "book_title" => $bookData->books_title
                ]
            ]
        ], 200);
    }




}
