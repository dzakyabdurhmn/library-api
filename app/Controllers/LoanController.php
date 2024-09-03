<?php

namespace App\Controllers;

use App\Controllers\CoreController;

class LoanController extends CoreController
{
    protected $reportController;

    public function __construct()
    {
        $this->reportController = new ReportController();
    }

    public function index()
    {
        // Fungsi index jika diperlukan
    }

    public function borrow_book()
    {
        $request = $this->request->getJSON();
        $db = db_connect();

        try {
            if (!isset($request->user_id) || !isset($request->borrow_book) || !is_array($request->borrow_book)) {
                return $this->respondWithValidationError('Invalid request format');
            }

            $user_id = $request->user_id;
            $books = $request->borrow_book;
            $response = [];
            $error = [];
            $bookIds = [];

            // Ambil data user
            $userQuery = "SELECT * FROM member WHERE user_id = ?";
            $user = $db->query($userQuery, [$user_id])->getRow();
            if (!$user) {
                return $this->respondWithNotFound('User not found');
            }

            // Generate transaction_id
            $todayDate = date('dmY');
            $lastIdQuery = "SELECT transaction_id FROM loan_user WHERE transaction_id LIKE '{$todayDate}-%' ORDER BY transaction_id DESC LIMIT 1";
            $lastIdResult = $db->query($lastIdQuery)->getRowArray();

            if ($lastIdResult) {
                $lastNumber = intval(substr($lastIdResult['transaction_id'], -3));
                $newNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
            } else {
                $newNumber = '001';
            }

            $transaction_id = $todayDate . '-' . $newNumber;

            // Masukkan data ke tabel loan_user (hanya sekali)
            $loanDate = date('Y-m-d H:i:s');
            $insertLoanUserQuery = "
                INSERT INTO loan_user (user_id, loan_date, transaction_id, username, email, full_name, address)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ";
            $db->query($insertLoanUserQuery, [
                $user_id,
                $loanDate,
                $transaction_id,
                $user->username,
                $user->email,
                $user->full_name,
                $user->address
            ]);

            $loan_id = $db->insertID(); // Dapatkan loan_id yang baru saja dimasukkan

            foreach ($books as $book) {
                if (!isset($book->book_id) || !isset($book->period)) {
                    $error[] = "Book ID or period is missing in the request";
                    continue;
                }

                $book_id = $book->book_id;
                $period = $book->period;

                if (in_array($book_id, $bookIds)) {
                    $error[] = "Duplicate book ID {$book_id} in the request";
                    continue;
                }

                $bookIds[] = $book_id;

                // Cek apakah pengguna masih meminjam buku ini
                $activeLoanQuery = "SELECT * FROM loan_user lu JOIN loan_book lb ON lu.loan_id = lb.loan_id WHERE lu.user_id = ? AND lb.book_id = ? AND lb.status = 'Borrowed'";
                $activeLoan = $db->query($activeLoanQuery, [$user_id, $book_id])->getRow();
                if ($activeLoan) {
                    $error[] = "User ID {$user_id} is already borrowing book ID {$book_id}";
                    continue;
                }

                // Ambil data buku
                $bookQuery = "SELECT cb.*, a.author_name, a.biography AS author_biography, p.publisher_name, p.address AS publisher_address, p.phone AS publisher_phone, p.email AS publisher_email
                              FROM catalog_books cb
                              LEFT JOIN author a ON cb.author_id = a.author_id
                              LEFT JOIN publisher p ON cb.publisher_id = p.publisher_id
                              WHERE cb.book_id = ?";
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

                // Hitung tanggal pengembalian
                $borrowDate = date('Y-m-d');
                $retrunDate = date('Y-m-d', strtotime("+{$period} days"));

                // Masukkan data lengkap ke tabel loan_book (untuk setiap buku)
                $insertLoanBookQuery = "
                    INSERT INTO loan_book (loan_id, book_id, book_title, publisher_name, publisher_address, publisher_phone, publisher_email, publication_year, isbn, author_name, author_biography, status, period, price, borrow_date, retrun_date, amercement)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Borrowed', ?, 0, ?, ?, 0)
                ";
                $db->query($insertLoanBookQuery, [
                    $loan_id,
                    $book_id,
                    $bookData->title,
                    $bookData->publisher_name,
                    $bookData->publisher_address,
                    $bookData->publisher_phone,
                    $bookData->publisher_email,
                    $bookData->publication_year,
                    $bookData->isbn,
                    $bookData->author_name,
                    $bookData->author_biography,
                    $period,
                    $borrowDate,
                    $retrunDate
                ]);

                // Ambil data lengkap untuk respons, termasuk data user
                $loanDetailQuery = "
                    SELECT 
                        lu.loan_id, 
                        lu.user_id, 
                        lu.transaction_id,
                        lu.username,
                        lu.email,
                        lu.full_name,
                        lu.address,
                        lb.book_title as book, 
                        lu.loan_date, 
                        lb.status,
                        lb.period,
                        lb.price,
                        lb.borrow_date,
                        lb.retrun_date
                    FROM loan_user lu
                    JOIN loan_book lb ON lu.loan_id = lb.loan_id
                    WHERE lu.loan_id = ? AND lb.book_id = ?
                ";
                $loanDetail = $db->query($loanDetailQuery, [$loan_id, $book_id])->getRowArray();

                if ($loanDetail) {
                    $response[] = $loanDetail;
                } else {
                    $error[] = "Failed to retrieve loan details for book ID {$book_id}";
                }
            }

            if (!empty($error)) {
                return $this->respondWithValidationError('Errors occurred', $error);
            }

            return $this->respondWithSuccess('Books successfully borrowed', $response);

        } catch (\Throwable $th) {
            return $this->respondWithValidationError('An error occurred', ['exception' => $th->getMessage()]);
        }
    }

    public function return_book()
    {
        $request = $this->request->getJSON();
        $db = db_connect();

        if (!isset($request->user_id) || !isset($request->return_book) || !is_array($request->return_book)) {
            return $this->respondWithValidationError('Invalid request format');
        }

        $user_id = $request->user_id;
        $books = $request->return_book;
        $response = [];
        $error = [];

        // Ambil data user
        $userQuery = "SELECT * FROM member WHERE user_id = ?";
        $user = $db->query($userQuery, [$user_id])->getRow();
        if (!$user) {
            return $this->respondWithNotFound('User not found');
        }

        foreach ($books as $book) {
            if (!isset($book->book_id) || !isset($book->status)) {
                $error[] = "Book ID or status is missing in the request";
                continue;
            }

            $book_id = $book->book_id;
            $status = $book->status;

            // Validasi peminjaman
            $loanQuery = "SELECT * FROM loan_user lu JOIN loan_book lb ON lu.loan_id = lb.loan_id WHERE lu.user_id = ? AND lb.book_id = ? AND lb.status = 'Borrowed'";
            $loanData = $db->query($loanQuery, [$user_id, $book_id])->getRow();
            if (!$loanData) {
                $error[] = "No active loan record found for user ID {$user_id} and book ID {$book_id}";
                continue;
            }

            // Hitung denda jika melewati tenggat waktu
            $currentDate = date('Y-m-d');
            $retrunDate = $loanData->retrun_date;

            if (strtotime($currentDate) > strtotime($retrunDate)) {
                $lateDays = (strtotime($currentDate) - strtotime($retrunDate)) / (60 * 60 * 24);
                // Misalnya denda tetap, jika ada
                $amercement = 0; // Set denda ke 0 atau gunakan denda tetap

                // Update denda di tabel loan_book jika ada aturan denda tetap
                $updateAmercementQuery = "UPDATE loan_book SET amercement = ? WHERE loan_id = ? AND book_id = ?";
                $db->query($updateAmercementQuery, [$amercement, $loanData->loan_id, $book_id]);
            }

            // Tambah stock_quantity hanya jika status buku adalah 'Good'
            if ($status === 'Good') {
                $updateStockQuery = "UPDATE catalog_books SET stock_quantity = stock_quantity + 1 WHERE book_id = ?";
                $db->query($updateStockQuery, [$loanData->book_id]);
            }

            // Perbarui status di tabel loan_book
            $updateLoanBookQuery = "UPDATE loan_book SET status = ? WHERE loan_id = ? AND book_id = ?";
            $db->query($updateLoanBookQuery, [$status, $loanData->loan_id, $book_id]);

            // Ambil data lengkap untuk respons, termasuk data user
            $loanDetailQuery = "
                SELECT 
                    lu.loan_id, 
                    lu.user_id, 
                    lu.username,
                    lu.email,
                    lu.full_name,
                    lu.address,
                    lb.book_title as book, 
                    lu.loan_date, 
                    lb.status,
                    lb.amercement
                FROM loan_user lu
                JOIN loan_book lb ON lu.loan_id = lb.loan_id
                WHERE lu.loan_id = ? AND lb.book_id = ?
            ";
            $loanDetail = $db->query($loanDetailQuery, [$loanData->loan_id, $book_id])->getRowArray();

            if ($loanDetail) {
                $response[] = $loanDetail;
            } else {
                $error[] = "Failed to retrieve loan details for book ID {$book_id}";
            }
        }

        if (!empty($error)) {
            return $this->respondWithValidationError('Errors occurred', $error);
        }

        return $this->respondWithSuccess('Books successfully returned', $response);
    }
}
