<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use Config\Database;

class LoanController extends CoreController
{
    public function borrow_book()
    {
        $request = $this->request->getJSON();

        if (empty($request->member_id) || empty($request->borrow_book)) {
            return $this->respondWithError("Member ID and book details are required.", null, 400);
        }

        $memberId = $request->member_id;
        $borrowBooks = $request->borrow_book;

        $db = Database::connect();

        // Cek total buku yang sedang dipinjam oleh member
        $currentLoansQuery = $db->query("SELECT COUNT(*) as total FROM loan_detail WHERE loan_detail_status = 'Borrowed' AND loan_detail_loan_transaction_code IN (SELECT loan_detail_loan_transaction_code FROM loan_detail WHERE loan_detail_status = 'Borrowed')");
        $currentLoans = $currentLoansQuery->getRow()->total;

        // Periksa apakah sudah mencapai batas
        if ($currentLoans >= 3) {
            return $this->respondWithError("You cannot borrow more than 3 books at a time.", null, 400);
        }

        // Batasi jumlah buku yang dapat dipinjam
        $booksToBorrow = array_filter($borrowBooks, function ($book) {
            return isset($book->book_id);
        });

        // Validasi total buku yang ingin dipinjam
        if (count($booksToBorrow) + $currentLoans > 3) {
            return $this->respondWithError("You can only borrow " . (3 - $currentLoans) . " more book(s).", null, 400);
        }

        $db->transStart();

        // Generate transaction code
        $transactionCode = uniqid('loan_');

        // Insert data ke tabel loan
        $db->query("INSERT INTO loan (loan_member_id, loan_transaction_code, loan_date) VALUES (?, ?, NOW())", [$memberId, $transactionCode]);
        $loanId = $db->insertID(); // Dapatkan ID peminjaman

        foreach ($booksToBorrow as $book) {
            $bookId = $book->book_id;

            // Cek stok buku
            $stockQuery = $db->query("SELECT books_stock_quantity FROM books WHERE book_id = ?", [$bookId]);
            $stock = $stockQuery->getRow()->books_stock_quantity;

            if ($stock <= 0) {
                return $this->respondWithError("Book ID $bookId is out of stock.", null, 400);
            }

            // Insert detail peminjaman
            $db->query("INSERT INTO loan_detail (loan_detail_book_id, loan_detail_book_title, loan_detail_borrow_date, loan_detail_status, loan_detail_period, loan_detail_loan_transaction_code) 
                    VALUES (?, (SELECT books_title FROM books WHERE book_id = ?), NOW(), 'Borrowed', 0, ?)", [$bookId, $bookId, $transactionCode]);

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
        $request = $this->request->getJSON();

        if (empty($request->member_id) || empty($request->borrow_book)) {
            return $this->respondWithError("Member ID and book details are required.", null, 400);
        }

        $memberId = $request->member_id;
        $returnBooks = $request->borrow_book;

        $db = Database::connect();
        $db->transStart();

        foreach ($returnBooks as $book) {
            $bookId = $book->book_id;
            $status = $book->status;

            // Cek status buku
            if ($status === 'Broken') {
                // Jika rusak, jangan tambah stok
                $db->query("UPDATE loan_detail SET loan_detail_status = 'Broken', loan_detail_return_date = NOW() WHERE loan_detail_book_id = ? AND loan_detail_status = 'Borrowed'", [$bookId]);
            } else {
                // Jika baik, tambah stok
                $db->query("UPDATE loan_detail SET loan_detail_status = 'Returned', loan_detail_return_date = NOW() WHERE loan_detail_book_id = ? AND loan_detail_status = 'Borrowed'", [$bookId]);
                $db->query("UPDATE books SET books_stock_quantity = books_stock_quantity + 1 WHERE book_id = ?", [$bookId]);
            }
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->respondWithError("Failed to return books.", null, 500);
        }

        return $this->respondWithSuccess("Books returned successfully.");
    }
}
