<?php

namespace App\Controllers;

use App\Controllers\CoreController;
use CodeIgniter\HTTP\ResponseInterface;

class LoanController extends CoreController
{


    public function index()
    {
        $db = db_connect();

        // Get query parameters for pagination, search, and filters
        $page = $this->request->getGet('page') ?: 1;
        $perPage = $this->request->getGet('per_page') ?: 10;
        $search = $this->request->getGet('search') ?: '';
        $status = $this->request->getGet('status') ?: '';

        // Build the base query
        $query = "SELECT l.loan_id, l.loan_member_id AS user_id, l.loan_transaction_code AS transaction_code, l.loan_member_username AS username, l.loan_member_email AS email, l.loan_member_full_name AS full_name, l.loan_member_address AS address, ld.loan_detail_book_id AS book_id, ld.loan_detail_book_title AS book_title, l.loan_date, ld.loan_detail_status AS status, ld.loan_detail_period AS period, ld.loan_detail_borrow_date AS borrow_date, ld.loan_detail_return_date AS return_date
                  FROM loan l
                  JOIN loan_detail ld ON l.loan_id = ld.loan_id
                  WHERE 1=1";

        // Apply search filter
        if ($search) {
            $query .= " AND (l.loan_member_username LIKE '%$search%' OR l.loan_member_email LIKE '%$search%' OR ld.loan_detail_book_title LIKE '%$search%')";
        }

        // Apply status filter
        if ($status) {
            $query .= " AND ld.loan_detail_status = '$status'";
        }

        // Get total count before applying limit and offset for pagination
        $totalQuery = "SELECT COUNT(*) as total FROM ($query) as subquery";
        $totalResult = $db->query($totalQuery)->getRow();
        $total = $totalResult->total;

        // Apply pagination
        $offset = ($page - 1) * $perPage;
        $query .= " LIMIT $perPage OFFSET $offset";

        // Execute the query
        $results = $db->query($query)->getResultArray();

        // Reorganize each loan record to place book_id above full_name
        $loans = array_map(function ($loan) {
            return [
                'user_id' => $loan['user_id'],
                'user_username' => $loan['username'],
                'user_email' => $loan['email'],
                'user_address' => $loan['address'],
                'user_full_name' => $loan['full_name'],
                'loan_id' => $loan['loan_id'],
                'transaction_code' => $loan['transaction_code'],
                'book_title' => $loan['book_title'],
                'loan_date' => $loan['loan_date'],
                'status' => $loan['status'],
                'period' => $loan['period'],
                'borrow_date' => $loan['borrow_date'],
                'return_date' => $loan['return_date']
            ];
        }, $results);

        // Prepare the response
        $response = [
            'total' => number_format($total),
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => ceil($total / $perPage),
            'data' => $loans
        ];

        return $this->respond($response, ResponseInterface::HTTP_OK);
    }

    public function detail()
    {
        $db = db_connect();
        $loan_id = $this->request->getGet('loan_id');
        $user_id = $this->request->getGet('user_id');

        // Validate input
        if (!$loan_id && !$user_id) {
            return $this->respondWithValidationError('Please provide either loan_id or user_id');
        }

        // Query to get loan data
        $loanQuery = "SELECT * FROM loan WHERE 1=1";
        $loanParams = [];

        if ($loan_id) {
            $loanQuery .= " AND loan_id = ?";
            $loanParams[] = $loan_id;
        }

        if ($user_id) {
            $loanQuery .= " AND loan_member_id = ?";
            $loanParams[] = $user_id;
        }

        $loanData = $db->query($loanQuery, $loanParams)->getRowArray();

        if (!$loanData) {
            return $this->respondWithNotFound('Loan not found');
        }

        // Query to get book data
        $bookQuery = "SELECT * FROM loan_detail WHERE loan_id = ?";
        $bookData = $db->query($bookQuery, [$loanData['loan_id']])->getResultArray();

        // Query to get user data
        $userQuery = "SELECT * FROM member WHERE member_id = ?";
        $userData = $db->query($userQuery, [$loanData['loan_member_id']])->getRowArray();

        // Prepare book data
        $books = array_map(function ($book) {
            return [
                'book_id' => $book['loan_detail_book_id'],
                'book_title' => $book['loan_detail_book_title'],
                'book_publication_year' => $book['loan_detail_book_publication_year'] ?? '',
                'book_isbn' => $book['loan_detail_book_isbn'] ?? '',
                'loan_status' => $book['loan_detail_status'],
                'loan_borrow_date' => $book['loan_detail_borrow_date'],
                'loan_return_date' => $book['loan_detail_return_date'],
                'loan_period' => $book['loan_detail_period']
            ];
        }, $bookData);

        // Prepare loans data
        $loans = [
            'user_id' => $loanData['loan_member_id'] ?? '',
            'user_username' => $loanData['loan_member_username'] ?? '',
            'user_email' => $loanData['loan_member_email'] ?? '',
            'user_address' => $loanData['loan_member_address'] ?? '',
            'user_full_name' => $loanData['loan_member_full_name'] ?? '',
            'return_book' => $books
        ];

        return $this->respondWithSuccess('Books successfully borrowed', $loans);
    }



    public function borrow_book()
    {
        $db = \Config\Database::connect();

        // Define validation rules
        $rules = [
            'member_id' => 'required|integer',
            array(
                'borrow_book' => 'required',
            ),
            'borrow_book.*.book_id' => 'required|integer'
        ];

        // Validate request input
        if (!$this->validate($rules)) {
            return $this->respondWithValidationError('Validation errors', $this->validator->getErrors());
        }

        $member_id = $this->request->getVar('member_id');
        $books = $this->request->getVar('borrow_book');

        // Check if member exists
        $memberExists = $db->query("SELECT COUNT(*) as count FROM member WHERE member_id = ?", [$member_id])->getRow()->count;

        if ($memberExists == 0) {
            return $this->respondWithError('Failed to borrow book: Member not found.', null, 404);
        }

        $response = [];
        $errors = [];
        $bookIds = [];

        // Generate transaction code
        $todayDate = date('dmY');
        $lastIdQuery = "SELECT loan_transaction_code FROM loan WHERE loan_transaction_code LIKE '{$todayDate}-%' ORDER BY loan_transaction_code DESC LIMIT 1";
        $lastIdResult = $db->query($lastIdQuery)->getRowArray();
        $newNumber = $lastIdResult ? str_pad(intval(substr($lastIdResult['loan_transaction_code'], -3)) + 1, 3, '0', STR_PAD_LEFT) : '001';
        $transaction_code = $todayDate . '-' . $newNumber;

        // Start database transaction
        $db->transStart();

        // Insert loan record
        $loanDate = date('Y-m-d H:i:s');
        $insertLoanQuery = "
        INSERT INTO loan (loan_member_id, loan_date, loan_transaction_code, loan_member_username, loan_member_email, loan_member_full_name, loan_member_address)
        SELECT member_id, ?, ?, member_username, member_email, member_full_name, member_address 
        FROM member WHERE member_id = ?
    ";
        $db->query($insertLoanQuery, [$loanDate, $transaction_code, $member_id]);
        $loan_id = $db->insertID();

        // Loop through each book in the borrow_book array
        foreach ($books as $book) {
            $book_id = $book['book_id'];

            // Check if book exists
            $bookExists = $db->query("SELECT COUNT(*) as count FROM books WHERE book_id = ?", [$book_id])->getRow()->count;

            if ($bookExists == 0) {
                $errors[] = "Book ID {$book_id} not found.";
                continue;
            }

            // Check if member is already borrowing this book
            $activeLoanQuery = "SELECT COUNT(*) as count FROM loan l JOIN loan_detail ld ON l.loan_id = ld.loan_id WHERE l.loan_member_id = ? AND ld.loan_detail_book_id = ? AND ld.loan_detail_status = 'Borrowed'";
            $activeLoanCount = $db->query($activeLoanQuery, [$member_id, $book_id])->getRow()->count;

            if ($activeLoanCount > 0) {
                $errors[] = "Member ID {$member_id} is already borrowing book ID {$book_id}.";
                continue;
            }

            // Get book data to ensure it has available stock
            $bookData = $db->query("SELECT * FROM books WHERE book_id = ?", [$book_id])->getRow();

            if ($bookData->books_stock_quantity <= 0) {
                $errors[] = "Book ID {$book_id} is out of stock.";
                continue;
            }

            // Reduce book stock
            $db->query("UPDATE books SET books_stock_quantity = books_stock_quantity - 1 WHERE book_id = ?", [$book_id]);

            // Calculate return date
            $borrowDate = date('Y-m-d');
            $returnDate = date('Y-m-d', strtotime("+7 days"));

            // Insert into loan_detail table
            $db->query("
            INSERT INTO loan_detail (
                loan_id, loan_detail_book_id, loan_detail_book_title, loan_detail_status, loan_detail_period, loan_detail_borrow_date, loan_detail_return_date
            ) VALUES (?, ?, ?, 'Borrowed', 7, ?, ?)
        ", [$loan_id, $book_id, $bookData->books_title, $borrowDate, $returnDate]);

            // Prepare response data for the borrowed book
            $response[] = [
                'loan_id' => $loan_id,
                'book_id' => $book_id,
                'book_title' => $bookData->books_title,
                'borrow_date' => $borrowDate,
                'return_date' => $returnDate
            ];
        }

        // Complete the database transaction
        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->respondWithError('Failed to borrow books due to a database error.');
        }

        if (!empty($errors)) {
            return $this->respondWithValidationError('Some books could not be borrowed', $errors);
        }

        return $this->respondWithSuccess('Books successfully borrowed', $response);
    }


    public function return_book()
    {
        $request = $this->request->getJSON();
        $member_id = $request->user_id;
        $returnBooks = $request->return_book;

        if (empty($member_id) || empty($returnBooks)) {
            return $this->respondWithValidationError('Invalid input', ['user_id' => 'User ID is required', 'return_book' => 'Return book list is required']);
        }

        $db = db_connect();
        $response = [];
        $errors = [];

        foreach ($returnBooks as $returnBook) {
            $book_id = $returnBook->book_id;
            $status = $returnBook->status;

            // Update status to returned status provided in the request
            $updateLoanDetailQuery = "UPDATE loan_detail SET loan_detail_status = ? WHERE loan_id = (SELECT loan_id FROM loan WHERE loan_member_id = ?) AND loan_detail_book_id = ?";
            $db->query($updateLoanDetailQuery, [$status, $member_id, $book_id]);

            // Increase book stock
            $updateStockQuery = "UPDATE books SET books_stock_quantity = books_stock_quantity + 1 WHERE book_id = ?";
            $db->query($updateStockQuery, [$book_id]);

            // Get loan details for response
            $loanDetailQuery = "
        SELECT 
            l.loan_id, 
            l.loan_member_id AS user_id, 
            l.loan_transaction_code AS transaction_code,
            l.loan_member_username AS username,
            l.loan_member_email AS email,
            l.loan_member_full_name AS full_name,
            l.loan_member_address AS address,
            ld.loan_detail_book_title AS book, 
            ld.loan_detail_status AS status,
            ld.loan_detail_period AS period,
            ld.loan_detail_borrow_date AS borrow_date,
            ld.loan_detail_return_date AS return_date
        FROM loan l
        JOIN loan_detail ld ON l.loan_id = ld.loan_id
        WHERE l.loan_member_id = ? AND ld.loan_detail_book_id = ?
    ";
            $loanDetail = $db->query($loanDetailQuery, [$member_id, $book_id])->getRowArray();

            if ($loanDetail) {
                $response[] = $loanDetail;
            } else {
                $errors[] = "Failed to retrieve loan details for book ID {$book_id}";
            }
        }

        if (!empty($errors)) {
            return $this->respondWithValidationError('Errors occurred', $errors);
        }

        return $this->respondWithSuccess('Books successfully returned', $response);
    }


}
