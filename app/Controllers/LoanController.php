<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourceController;

class LoanController extends ResourceController
{
    protected $format = 'json';

    public function borrow_book()
    {
        $request = $this->request->getJSON();
        $db = db_connect();

        if (!isset($request->user_id) || !isset($request->borrow_book)) {
            return $this->failValidationErrors("Invalid request format");
        }

        $user_id = $request->user_id;
        $books = $request->borrow_book;
        $response = [];
        $error = [];
        $bookIds = [];

        // Validasi user
        $userQuery = "SELECT * FROM member WHERE user_id = ?";
        $user = $db->query($userQuery, [$user_id])->getRow();
        if (!$user) {
            return $this->failNotFound("User not found");
        }

        foreach ($books as $book) {
            if (!isset($book->book_id)) {
                $error[] = "Book ID is missing in the request";
                continue;
            }

            $book_id = $book->book_id;

            // Cek duplikasi book_id dalam request
            if (in_array($book_id, $bookIds)) {
                $error[] = "Duplicate book ID {$book_id} in the request";
                continue;
            }

            $bookIds[] = $book_id;

            // Cek apakah pengguna masih meminjam buku ini
            $activeLoanQuery = "SELECT * FROM loan WHERE user_id = ? AND book_id = ?";
            $activeLoan = $db->query($activeLoanQuery, [$user_id, $book_id])->getRow();
            if ($activeLoan) {
                $error[] = "User ID {$user_id} is already borrowing book ID {$book_id}";
                continue;
            }

            // Validasi buku
            $bookQuery = "SELECT * FROM catalog_books WHERE book_id = ?";
            $bookData = $db->query($bookQuery, [$book_id])->getRow();
            if (!$bookData) {
                $error[] = "Book with ID {$book_id} not found";
                continue;
            }

            if ($bookData->stock_quantity <= 0) {
                $error[] = "Book with ID {$book_id} is out of stock";
                continue;
            }

            // Kurangi stock_quantity
            $updateStockQuery = "UPDATE catalog_books SET stock_quantity = stock_quantity - 1 WHERE book_id = ?";
            $db->query($updateStockQuery, [$book_id]);

            // Masukkan data peminjaman ke tabel loan
            $loanDate = date('Y-m-d H:i:s');
            $insertLoanQuery = "INSERT INTO loan (user_id, book_id, loan_date, status) VALUES (?, ?, ?, 'borrowed')";
            $db->query($insertLoanQuery, [$user_id, $book_id, $loanDate]);

            // Masukkan data peminjaman ke tabel all_loan
            $insertAllLoanQuery = "INSERT INTO all_loan (user_id, book_id, loan_date) VALUES (?, ?, ?)";
            $db->query($insertAllLoanQuery, [$user_id, $book_id, $loanDate]);

            // Ambil data lengkap untuk respons
            $loanId = $db->insertID();
            $loanDetailQuery = "
                SELECT 
                    loan.loan_id, 
                    member.full_name as user, 
                    catalog_books.title as book, 
                    loan.loan_date, 
                    loan.status
                FROM loan
                JOIN member ON loan.user_id = member.user_id
                JOIN catalog_books ON loan.book_id = catalog_books.book_id
                WHERE loan.loan_id = ?
            ";
            $loanDetail = $db->query($loanDetailQuery, [$loanId])->getRowArray();

            $response[] = $loanDetail;
        }

        if (!empty($error)) {
            return $this->failValidationErrors($error);
        }

        return $this->respondCreated(['message' => 'Books successfully borrowed', 'data' => $response]);
    }

    public function return_book()
    {
        $request = $this->request->getJSON();
        $db = db_connect();

        if (!isset($request->user_id) || !isset($request->return_book)) {
            return $this->failValidationErrors("Invalid request format");
        }

        $user_id = $request->user_id;
        $books = $request->return_book;
        $response = [];
        $error = [];

        // Validasi user
        $userQuery = "SELECT * FROM member WHERE user_id = ?";
        $user = $db->query($userQuery, [$user_id])->getRow();
        if (!$user) {
            return $this->failNotFound("User not found");
        }

        foreach ($books as $book) {
            if (!isset($book->book_id)) {
                $error[] = "Book ID is missing in the request";
                continue;
            }

            $book_id = $book->book_id;

            // Validasi peminjaman
            $loanQuery = "SELECT * FROM loan WHERE user_id = ? AND book_id = ?";
            $loanData = $db->query($loanQuery, [$user_id, $book_id])->getRow();
            if (!$loanData) {
                $error[] = "No active loan record found for user ID {$user_id} and book ID {$book_id}";
                continue;
            }

            // Validasi buku
            $bookQuery = "SELECT * FROM catalog_books WHERE book_id = ?";
            $bookData = $db->query($bookQuery, [$loanData->book_id])->getRow();
            if (!$bookData) {
                $error[] = "Book with ID {$loanData->book_id} not found";
                continue;
            }

            // Tambah stock_quantity
            $updateStockQuery = "UPDATE catalog_books SET stock_quantity = stock_quantity + 1 WHERE book_id = ?";
            $db->query($updateStockQuery, [$loanData->book_id]);

            // Hapus data peminjaman dari tabel loan
            $deleteLoanQuery = "DELETE FROM loan WHERE loan_id = ?";
            $db->query($deleteLoanQuery, [$loanData->loan_id]);

            // Perbarui return_date di tabel all_loan
            $returnDate = date('Y-m-d H:i:s');
            $updateAllLoanQuery = "UPDATE all_loan SET return_date = ? WHERE user_id = ? AND book_id = ? AND return_date IS NULL";
            $db->query($updateAllLoanQuery, [$returnDate, $user_id, $book_id]);

            // Ambil data lengkap untuk respons
            $loanDetail = [
                'loan_id' => $loanData->loan_id,
                'user' => $user->full_name,
                'book' => $bookData->title,
                'loan_date' => $loanData->loan_date,
                'return_date' => $returnDate
            ];

            $response[] = $loanDetail;
        }

        if (!empty($error)) {
            return $this->failValidationErrors($error);
        }

        return $this->respondCreated(['message' => 'Books successfully returned', 'data' => $response]);
    }
}
