<?php
namespace App\Controllers;

use CodeIgniter\Controller;
use Config\Database;

class ReportController extends Controller
{
    public function most_borrowed_books()
    {
        $db = Database::connect();
        $query = "
            SELECT loan_detail_book_id as book_id, COUNT(*) as borrow_count
            FROM loan_detail
            WHERE loan_detail_status = 'Borrowed'
            GROUP BY loan_detail_book_id
            ORDER BY borrow_count DESC
            LIMIT 10";
        $borrowedBooks = $db->query($query)->getResultArray();

        $detailedBooks = [];
        foreach ($borrowedBooks as $borrowedBook) {
            $bookId = $borrowedBook['book_id'];

            $bookQuery = "SELECT * FROM books WHERE book_id = ?";
            $bookDetails = $db->query($bookQuery, [$bookId])->getRowArray();

            if ($bookDetails) {
                $detailedBooks[] = [
                    'book_id' => $bookDetails['book_id'],
                    'book_title' => $bookDetails['books_title'],
                    'borrow_count' => $borrowedBook['borrow_count'],
                    'publisher_name' => $bookDetails['books_publisher_id'],
                    'publication_year' => $bookDetails['books_publication_year'],
                    'isbn' => $bookDetails['books_isbn'],
                    'price' => $bookDetails['books_price'],
                ];
            }
        }

        return $this->respondWithSuccess('Most borrowed books retrieved.', ['data' => $detailedBooks]);
    }

    public function least_borrowed_books()
    {
        $db = Database::connect();
        $query = "
            SELECT loan_detail_book_id as book_id, COUNT(*) as borrow_count
            FROM loan_detail
            WHERE loan_detail_status = 'Borrowed'
            GROUP BY loan_detail_book_id
            ORDER BY borrow_count ASC
            LIMIT 10";
        $borrowedBooks = $db->query($query)->getResultArray();

        $detailedBooks = [];
        foreach ($borrowedBooks as $borrowedBook) {
            $bookId = $borrowedBook['book_id'];

            $bookQuery = "SELECT * FROM books WHERE book_id = ?";
            $bookDetails = $db->query($bookQuery, [$bookId])->getRowArray();

            if ($bookDetails) {
                $detailedBooks[] = [
                    'book_id' => $bookDetails['book_id'],
                    'book_title' => $bookDetails['books_title'],
                    'borrow_count' => $borrowedBook['borrow_count'],
                    'publisher_name' => $bookDetails['books_publisher_id'],
                    'publication_year' => $bookDetails['books_publication_year'],
                    'isbn' => $bookDetails['books_isbn'],
                    'price' => $bookDetails['books_price'],
                ];
            }
        }

        return $this->respondWithSuccess('Least borrowed books retrieved.', ['data' => $detailedBooks]);
    }

    public function broken_missing_books()
    {
        $db = Database::connect();
        $query = "
            SELECT loan_detail_book_id as book_id, loan_detail_status, COUNT(*) as count
            FROM loan_detail
            WHERE loan_detail_status IN ('Broken', 'Missing')
            GROUP BY loan_detail_book_id, loan_detail_status";
        $brokenMissingBooks = $db->query($query)->getResultArray();

        $detailedBooks = [];
        foreach ($brokenMissingBooks as $book) {
            $bookId = $book['book_id'];

            $bookQuery = "SELECT * FROM books WHERE book_id = ?";
            $bookDetails = $db->query($bookQuery, [$bookId])->getRowArray();

            if ($bookDetails) {
                $detailedBooks[] = [
                    'book_id' => $bookDetails['book_id'],
                    'book_title' => $bookDetails['books_title'],
                    'status' => $book['loan_detail_status'],
                    'count' => $book['count'],
                    'publisher_name' => $bookDetails['books_publisher_id'],
                    'publication_year' => $bookDetails['books_publication_year'],
                    'isbn' => $bookDetails['books_isbn'],
                ];
            }
        }

        return $this->respondWithSuccess('Broken and missing books retrieved.', ['data' => $detailedBooks]);
    }

    public function most_active_users()
    {
        $db = Database::connect();
        $query = "
            SELECT loan_member_id, COUNT(*) as activity_count
            FROM loan
            WHERE loan_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
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

        return $this->respondWithSuccess('Most active users retrieved.', ['data' => $detailedMembers]);
    }

    public function inactive_users()
    {
        $db = Database::connect();
        $query = "
            SELECT member_id, member_username, member_email
            FROM member
            WHERE member_id NOT IN (
                SELECT loan_member_id FROM loan WHERE loan_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            )";
        $result = $db->query($query)->getResultArray();
        return $this->respondWithSuccess('Inactive users retrieved.', ['data' => $result]);
    }

    public function active_admins()
    {
        $db = Database::connect();
        $query = "
            SELECT admin_id, admin_username, admin_email
            FROM admin
            WHERE admin_id IN (
                SELECT admin_id FROM admin_token WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            )";
        $result = $db->query($query)->getResultArray();
        return $this->respondWithSuccess('Active admins retrieved.', ['data' => $result]);
    }

    protected function respondWithSuccess($message, $data, $code = 200)
    {
        return $this->response->setJSON([
            'status' => $code,
            'message' => $message,
            'result' => $data
        ]);
    }
}
