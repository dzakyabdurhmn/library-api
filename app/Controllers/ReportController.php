<?php
namespace App\Controllers;

use CodeIgniter\Controller;
use Config\Database;

class ReportController extends Controller
{
    protected function respondWithSuccess($message, $data, $code = 200)
    {
        return $this->response->setJSON([
            'status' => $code,
            'message' => $message,
            'result' => $data
        ]);
    }

    protected function respondWithError($message, $code = 400)
    {
        return $this->response->setJSON([
            'status' => $code,
            'message' => $message,
            'result' => null
        ]);
    }

    public function most_borrowed_books()
    {
        $db = Database::connect();

        // Ambil parameter dari query string
        $limit = $this->request->getVar('limit') ?? 10; // Default limit
        $page = $this->request->getVar('page') ?? 1; // Default page
        $offset = ($page - 1) * $limit;

        $query = "
            SELECT loan_detail_book_id as book_id, COUNT(*) as borrow_count
            FROM loan_detail
            WHERE loan_detail_status = 'Borrowed'
            GROUP BY loan_detail_book_id
            ORDER BY borrow_count DESC LIMIT ? OFFSET ?";

        $borrowedBooks = $db->query($query, [(int) $limit, (int) $offset])->getResultArray();

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

        // Total count for pagination
        $totalQuery = "
            SELECT COUNT(DISTINCT loan_detail_book_id) as total
            FROM loan_detail
            WHERE loan_detail_status = 'Borrowed'";
        $total = $db->query($totalQuery)->getRow()->total;

        $jumlah_page = ceil($total / $limit);
        $prev = ($page > 1) ? $page - 1 : null;
        $next = ($page < $jumlah_page) ? $page + 1 : null;

        return $this->respondWithSuccess('Most borrowed books retrieved.', [
            'data' => $detailedBooks,
            'pagination' => [
                'total_data' => (int) $total,
                'total_pages' => (int) $jumlah_page,
                'prev' => $prev,
                'page' => (int) $page,
                'next' => $next,
                'detail' => range(max(1, $page - 2), min($jumlah_page, $page + 2)),
                'start' => ($page - 1) * $limit + 1,
                'end' => min($page * $limit, $total),
            ]
        ]);
    }

    public function least_borrowed_books()
    {
        $db = Database::connect();

        // Ambil parameter dari query string
        $limit = $this->request->getVar('limit') ?? 10; // Default limit
        $page = $this->request->getVar('page') ?? 1; // Default page
        $offset = ($page - 1) * $limit;

        $query = "
            SELECT loan_detail_book_id as book_id, COUNT(*) as borrow_count
            FROM loan_detail
            WHERE loan_detail_status = 'Borrowed'
            GROUP BY loan_detail_book_id
            ORDER BY borrow_count ASC LIMIT ? OFFSET ?";

        $borrowedBooks = $db->query($query, [(int) $limit, (int) $offset])->getResultArray();

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

        // Total count for pagination
        $totalQuery = "
            SELECT COUNT(DISTINCT loan_detail_book_id) as total
            FROM loan_detail
            WHERE loan_detail_status = 'Borrowed'";
        $total = $db->query($totalQuery)->getRow()->total;

        $jumlah_page = ceil($total / $limit);
        $prev = ($page > 1) ? $page - 1 : null;
        $next = ($page < $jumlah_page) ? $page + 1 : null;

        return $this->respondWithSuccess('Least borrowed books retrieved.', [
            'data' => $detailedBooks,
            'pagination' => [
                'total_data' => (int) $total,
                'total_pages' => (int) $jumlah_page,
                'prev' => $prev,
                'page' => (int) $page,
                'next' => $next,
                'detail' => range(max(1, $page - 2), min($jumlah_page, $page + 2)),
                'start' => ($page - 1) * $limit + 1,
                'end' => min($page * $limit, $total),
            ]
        ]);
    }

    public function broken_missing_books()
    {
        $db = Database::connect();

        // Ambil parameter dari query string
        $limit = $this->request->getVar('limit') ?? 10; // Default limit
        $page = $this->request->getVar('page') ?? 1; // Default page
        $offset = ($page - 1) * $limit;

        // Start building the query
        $query = "
            SELECT loan_detail_book_id as book_id, loan_detail_status, COUNT(*) as count
            FROM loan_detail
            WHERE loan_detail_status IN ('Broken', 'Missing')
            GROUP BY loan_detail_book_id, loan_detail_status
            LIMIT ? OFFSET ?";

        $brokenMissingBooks = $db->query($query, [(int) $limit, (int) $offset])->getResultArray();

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

        // Total count for pagination
        $totalQuery = "
            SELECT COUNT(*) as total FROM loan_detail
            WHERE loan_detail_status IN ('Broken', 'Missing')";
        $total = $db->query($totalQuery)->getRow()->total;

        $jumlah_page = ceil($total / $limit);
        $prev = ($page > 1) ? $page - 1 : null;
        $next = ($page < $jumlah_page) ? $page + 1 : null;

        return $this->respondWithSuccess('Broken and missing books retrieved.', [
            'data' => $detailedBooks,
            'pagination' => [
                'total_data' => (int) $total,
                'total_pages' => (int) $jumlah_page,
                'prev' => $prev,
                'page' => (int) $page,
                'next' => $next,
                'detail' => range(max(1, $page - 2), min($jumlah_page, $page + 2)),
                'start' => ($page - 1) * $limit + 1,
                'end' => min($page * $limit, $total),
            ]
        ]);
    }

    public function most_active_users()
    {
        $db = Database::connect();

        // Ambil parameter dari query string
        $limit = $this->request->getVar('limit') ?? 10; // Default limit
        $page = $this->request->getVar('page') ?? 1; // Default page
        $offset = ($page - 1) * $limit;

        $query = "
            SELECT loan_member_id, COUNT(*) as activity_count
            FROM loan
            WHERE loan_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY loan_member_id
            ORDER BY activity_count DESC
            LIMIT ? OFFSET ?";

        $members = $db->query($query, [(int) $limit, (int) $offset])->getResultArray();

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

        // Total count for pagination
        $totalQuery = "
            SELECT COUNT(DISTINCT loan_member_id) as total
            FROM loan
            WHERE loan_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        $total = $db->query($totalQuery)->getRow()->total;

        $jumlah_page = ceil($total / $limit);
        $prev = ($page > 1) ? $page - 1 : null;
        $next = ($page < $jumlah_page) ? $page + 1 : null;

        return $this->respondWithSuccess('Most active users retrieved.', [
            'data' => $detailedMembers,
            'pagination' => [
                'total_data' => (int) $total,
                'total_pages' => (int) $jumlah_page,
                'prev' => $prev,
                'page' => (int) $page,
                'next' => $next,
                'detail' => range(max(1, $page - 2), min($jumlah_page, $page + 2)),
                'start' => ($page - 1) * $limit + 1,
                'end' => min($page * $limit, $total),
            ]
        ]);
    }

    public function inactive_users()
    {
        $db = Database::connect();

        // Ambil parameter dari query string
        $limit = $this->request->getVar('limit') ?? 10; // Default limit
        $page = $this->request->getVar('page') ?? 1; // Default page
        $offset = ($page - 1) * $limit;

        $query = "
            SELECT member_id, member_username, member_email
            FROM member
            WHERE member_id NOT IN (
                SELECT loan_member_id FROM loan WHERE loan_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            )
            LIMIT ? OFFSET ?";

        $result = $db->query($query, [(int) $limit, (int) $offset])->getResultArray();

        // Total count for pagination
        $totalQuery = "
            SELECT COUNT(*) as total FROM member
            WHERE member_id NOT IN (
                SELECT loan_member_id FROM loan WHERE loan_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            )";
        $total = $db->query($totalQuery)->getRow()->total;

        $jumlah_page = ceil($total / $limit);
        $prev = ($page > 1) ? $page - 1 : null;
        $next = ($page < $jumlah_page) ? $page + 1 : null;

        return $this->respondWithSuccess('Inactive users retrieved.', [
            'data' => $result,
            'pagination' => [
                'total_data' => (int) $total,
                'total_pages' => (int) $jumlah_page,
                'prev' => $prev,
                'page' => (int) $page,
                'next' => $next,
                'detail' => range(max(1, $page - 2), min($jumlah_page, $page + 2)),
                'start' => ($page - 1) * $limit + 1,
                'end' => min($page * $limit, $total),
            ]
        ]);
    }

    // public function active_admins()
    // {
    //     $db = Database::connect();

    //     // Ambil parameter dari query string
    //     $limit = $this->request->getVar('limit') ?? 10; // Default limit
    //     $page = $this->request->getVar('page') ?? 1; // Default page
    //     $offset = ($page - 1) * $limit;

    //     $query = "
    //         SELECT admin_id, admin_username, admin_email
    //         FROM admin
    //         WHERE admin_id IN (
    //             SELECT admin_id FROM admin_token WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    //         )
    //         LIMIT ? OFFSET ?";

    //     $result = $db->query($query, [(int) $limit, (int) $offset])->getResultArray();

    //     // Total count for pagination
    //     $totalQuery = "
    //         SELECT COUNT(*) as total FROM admin
    //         WHERE admin_id IN (
    //             SELECT admin_id FROM admin_token WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    //         )";
    //     $total = $db->query($totalQuery)->getRow()->total;

    //     $jumlah_page = ceil($total / $limit);
    //     $prev = ($page > 1) ? $page - 1 : null;
    //     $next = ($page < $jumlah_page) ? $page + 1 : null;

    //     return $this->respondWithSuccess('Active admins retrieved.', [
    //         'data' => $result,
    //         'pagination' => [
    //             'total_data' => (int) $total,
    //             'total_pages' => (int) $jumlah_page,
    //             'prev' => $prev,
    //             'page' => (int) $page,
    //             'next' => $next,
    //             'detail' => range(max(1, $page - 2), min($jumlah_page, $page + 2)),
    //             'start' => ($page - 1) * $limit + 1,
    //             'end' => min($page * $limit, $total),
    //         ]
    //     ]);
    // }



    public function detailed_member_activity()
    {
        $db = Database::connect();
        $limit = $this->request->getVar('limit') ?? 10; // Default limit
        $page = $this->request->getVar('page') ?? 1; // Default page
        $offset = ($page - 1) * $limit;

        // Ambil semua member dengan pagination
        $membersQuery = "SELECT member_id, member_username, member_email, member_full_name, member_address FROM member LIMIT ? OFFSET ?";
        $members = $db->query($membersQuery, [(int) $limit, (int) $offset])->getResultArray();

        // Menyimpan detail member
        $detailedMembers = [];
        foreach ($members as $member) {
            $memberId = $member['member_id'];

            // Hitung jumlah pinjaman per member
            $activityCountQuery = "SELECT COUNT(loan_id) as activity_count FROM loan WHERE loan_member_id = ?";
            $activityCount = $db->query($activityCountQuery, [$memberId])->getRow()->activity_count;

            // Ambil detail pinjaman member
            $loansQuery = "
            SELECT loan_id, loan_transaction_code, loan_date, loan_member_username, loan_member_email, loan_member_full_name, loan_member_address 
            FROM loan 
            WHERE loan_member_id = ?";
            $loans = $db->query($loansQuery, [$memberId])->getResultArray();

            // Menyusun detail member
            $detailedMembers[] = [
                'member_id' => $member['member_id'],
                'username' => $member['member_username'],
                'email' => $member['member_email'],
                'full_name' => $member['member_full_name'],
                'address' => $member['member_address'],
                'activity_count' => (int) $activityCount,
                'loans' => $loans // Detail pinjaman
            ];
        }

        // Total count untuk pagination
        $totalQuery = "SELECT COUNT(*) as total FROM member";
        $total = $db->query($totalQuery)->getRow()->total;

        $jumlah_page = ceil($total / $limit);
        $prev = ($page > 1) ? $page - 1 : null;
        $next = ($page < $jumlah_page) ? $page + 1 : null;

        return $this->respondWithSuccess('Detailed member activity retrieved.', [
            'data' => $detailedMembers,
            'pagination' => [
                'total_data' => (int) $total,
                'total_pages' => (int) $jumlah_page,
                'prev' => $prev,
                'page' => (int) $page,
                'next' => $next,
                'detail' => range(max(1, $page - 2), min($jumlah_page, $page + 2)),
                'start' => ($page - 1) * $limit + 1,
                'end' => min($page * $limit, $total),
            ]
        ]);
    }



    public function count_books_status()
    {
        $db = Database::connect();

        // Count damaged books
        $damagedQuery = "SELECT COUNT(*) as damaged_count FROM loan_detail WHERE loan_detail_status = 'Broken'";
        $damagedCount = $db->query($damagedQuery)->getRow()->damaged_count;

        // Count missing books
        $missingQuery = "SELECT COUNT(*) as missing_count FROM loan_detail WHERE loan_detail_status = 'Missing'";
        $missingCount = $db->query($missingQuery)->getRow()->missing_count;

        // Count currently borrowed books
        $currentlyBorrowedQuery = "SELECT COUNT(*) as borrowed_count FROM loan_detail WHERE loan_detail_status = 'Borrowed'";
        $currentlyBorrowedCount = $db->query($currentlyBorrowedQuery)->getRow()->borrowed_count;

        // Count total books in the library
        $totalBooksQuery = "SELECT COUNT(*) as total_books FROM books";
        $totalBooksCount = $db->query($totalBooksQuery)->getRow()->total_books;

        // Count total loans for today
        $todayActiveLoansQuery = "SELECT COUNT(*) as today_loans FROM loan WHERE DATE(loan_date) = CURDATE()";
        $todayActiveLoansCount = $db->query($todayActiveLoansQuery)->getRow()->today_loans;

        // Count total loans for this week
        $thisWeekActiveLoansQuery = "SELECT COUNT(*) as this_week_loans FROM loan WHERE YEARWEEK(loan_date, 1) = YEARWEEK(CURDATE(), 1)";
        $thisWeekActiveLoansCount = $db->query($thisWeekActiveLoansQuery)->getRow()->this_week_loans;

        // Count total members
        $totalMembersQuery = "SELECT COUNT(*) as total_members FROM member";
        $totalMembersCount = $db->query($totalMembersQuery)->getRow()->total_members;

        // Count total active admins
        $activeAdminsQuery = "SELECT COUNT(*) as active_admins FROM admin WHERE admin_id IN (SELECT admin_id FROM admin_token WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY))";
        $activeAdminsCount = $db->query($activeAdminsQuery)->getRow()->active_admins;

        // Count total loans in the last 30 days
        $totalLoansLast30DaysQuery = "SELECT COUNT(*) as total_loans FROM loan WHERE loan_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        $totalLoansLast30DaysCount = $db->query($totalLoansLast30DaysQuery)->getRow()->total_loans;

        // Count total publishers
        $totalPublishersQuery = "SELECT COUNT(*) as total_publishers FROM publisher";
        $totalPublishersCount = $db->query($totalPublishersQuery)->getRow()->total_publishers;

        // Count total authors
        $totalAuthorsQuery = "SELECT COUNT(*) as total_authors FROM author";
        $totalAuthorsCount = $db->query($totalAuthorsQuery)->getRow()->total_authors;

        // Prepare response data
        $result = [
          'data' => [
                'damaged_count' => (int) $damagedCount,
                'missing_count' => (int) $missingCount,
                'currently_borrowed_count' => (int) $currentlyBorrowedCount,
                'total_books_count' => (int) $totalBooksCount,
                'today_active_loans_count' => (int) $todayActiveLoansCount,
                'this_week_active_loans_count' => (int) $thisWeekActiveLoansCount,
                'total_members_count' => (int) $totalMembersCount,
                'active_admins_count' => (int) $activeAdminsCount,
                'total_loans_last_30_days' => (int) $totalLoansLast30DaysCount,
                'total_publishers_count' => (int) $totalPublishersCount,
                'total_authors_count' => (int) $totalAuthorsCount,
          ]
        ];

        return $this->respondWithSuccess('Report status counts retrieved.', $result);
    }


}

