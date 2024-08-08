<?php

namespace App\Controllers;

use App\Controllers\ReportController;

class LoanController extends CoreController
{
    protected $reportController;

    public function __construct()
    {
        $this->reportController = new ReportController();
    }

    public function borrow_book()
    {
        $request = $this->request->getJSON();
        $db = db_connect();


        try {

        if (!isset($request->user_id) || !isset($request->borrow_book) || !is_array($request->borrow_book)) {
            return $this->response->setJSON([
                'status' => 400,
                'message' => 'Invalid request format'
            ])->setStatusCode(400);
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
            return $this->response->setJSON([
                'status' => 404,
                'message' => 'User not found'
            ])->setStatusCode(404);
        };

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
            $activeLoanQuery = "SELECT * FROM loan WHERE user_id = ? AND book_id = ? AND status = 'borrowed'";
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

            // Panggil fungsi generate_report dari ReportController
            $reportResult = $this->reportController->generate_report($user_id, $book_id);

            // Cek jika ada error dari reportResult
            if ($reportResult['status'] !== 201) {
                $error[] = $reportResult['message'];
                continue;
            }

            // Ambil data lengkap untuk respons
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
                WHERE loan.user_id = ? AND loan.book_id = ?
            ";
            $loanDetail = $db->query($loanDetailQuery, [$user_id, $book_id])->getRowArray();

            if ($loanDetail) {
                $response[] = $loanDetail;
            } else {
                $error[] = "Failed to retrieve loan details for book ID {$book_id}";
            }
        }

        if (!empty($error)) {
            return $this->response->setJSON([
                'status' => 400,
                'message' => 'Errors occurred',
                'errors' => $error
            ])->setStatusCode(400);
        }

        return $this->response->setJSON([
            'status' => 200,
            'message' => 'Books successfully borrowed',
            'data' => $response
        ])->setStatusCode(200);
            
        } catch (\Throwable $th) {
            print_r('error'); die;
        }
       
    }

public function return_book()
{
    $request = $this->request->getJSON();
    $db = db_connect();

    if (!isset($request->user_id) || !isset($request->return_book) || !is_array($request->return_book)) {
        return $this->response->setJSON([
            'status' => 400,
            'message' => 'Invalid request format'
        ])->setStatusCode(400);
    }

    $user_id = $request->user_id;
    $books = $request->return_book;
    $response = [];
    $error = [];

    // Validasi user
    $userQuery = "SELECT * FROM member WHERE user_id = ?";
    $user = $db->query($userQuery, [$user_id])->getRow();
    if (!$user) {
        return $this->response->setJSON([
            'status' => 404,
            'message' => 'User not found'
        ])->setStatusCode(404);
    }

    foreach ($books as $book) {
        if (!isset($book->book_id) || !isset($book->status)) {
            $error[] = "Book ID or status is missing in the request";
            continue;
        }

        $book_id = $book->book_id;
        $status = $book->status;

        // Validasi peminjaman
        $loanQuery = "SELECT * FROM loan WHERE user_id = ? AND book_id = ? AND status = 'borrowed'";
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

        // Tambah stock_quantity hanya jika status buku adalah 'Good'
        if ($status === 'Good') {
            $updateStockQuery = "UPDATE catalog_books SET stock_quantity = stock_quantity + 1 WHERE book_id = ?";
            $db->query($updateStockQuery, [$loanData->book_id]);
        }

        // Perbarui status dan tanggal pengembalian di tabel loan
        $returnDate = date('Y-m-d H:i:s');
        $updateLoanQuery = "UPDATE loan SET loan_date = ?, status = ? WHERE loan_id = ?";
        $db->query($updateLoanQuery, [$returnDate, $status, $loanData->loan_id]);

        // Ambil data lengkap untuk respons
        $loanDetail = [
            'loan_id' => $loanData->loan_id,
            'user' => $user->full_name,
            'book' => $bookData->title,
            'loan_date' => $loanData->loan_date,
            'status' => $status
        ];

        $response[] = $loanDetail;
    }

    if (!empty($error)) {
        return $this->response->setJSON([
            'status' => 400,
            'message' => 'Errors occurred',
            'errors' => $error
        ])->setStatusCode(400);
    }

    return $this->response->setJSON([
        'status' => 200,
        'message' => 'Books successfully returned',
        'data' => $response
    ])->setStatusCode(200);
}




}
