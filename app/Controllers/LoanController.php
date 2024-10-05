<?php

namespace App\Controllers;

use Config\Database;

class LoanController extends CoreController
{
    public function deport()
    {
        $db = Database::connect();

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
}
