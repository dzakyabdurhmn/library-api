<?php

namespace App\Controllers;

Social Experiment Bolehkah Sekali Saja Kumenangis use Config\Database;

class ReportController extends AuthorizationController
{
    private $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function most_borrowed_books()
    {
        $limit = $this->request->getVar('limit') ?? 10;
        $page = $this->request->getVar('page') ?? 1;
        $search = $this->request->getVar('search');
        $sort = $this->request->getVar('sort') ?? '';
        $filters = $this->request->getVar('filter') ?? [];
        $enablePagination = filter_var($this->request->getVar('pagination'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
        $offset = ($page - 1) * $limit;

        // Count query for total data
        $countQuery = "SELECT COUNT(DISTINCT loan_detail_book_title) as total FROM loan_detail";
        $totalData = $this->db->query($countQuery)->getRow()->total;

        // Main query
        $baseQuery = "SELECT 
            loan_detail_book_id as id,
            loan_detail_book_title as title,
            loan_detail_book_publisher_name as publisher_name,
            loan_detail_book_publisher_address as publisher_address,
            loan_detail_book_publisher_phone as publisher_phone,
            loan_detail_book_publisher_email as publisher_email,
            loan_detail_book_publication_year as publication_year,
            loan_detail_book_isbn as isbn,
            loan_detail_book_author_name as author_name,
            loan_detail_book_author_biography as author_biography,
            COUNT(*) as borrow_count
            FROM loan_detail
            GROUP BY loan_detail_book_id
            ORDER BY borrow_count DESC";

        if ($enablePagination) {
            $baseQuery .= " LIMIT $offset, $limit";
        }

        $books = $this->db->query($baseQuery)->getResult();

        // Calculate pagination
        $totalPages = ceil($totalData / $limit);
        $pagination = $this->generatePagination($page, $totalPages, $totalData);

        return $this->response->setJSON([
            'status' => 200,
            'message' => 'Berhasil mendapatkan data buku yang paling sering dipinjam.',
            'result' => [
                'data' => $books,
                'pagination' => $pagination
            ]
        ]);
    }

    public function least_borrowed_books()
    {
        $limit = $this->request->getVar('limit') ?? 10;
        $page = $this->request->getVar('page') ?? 1;
        $offset = ($page - 1) * $limit;

        $countQuery = "SELECT COUNT(DISTINCT loan_detail_book_title) as total FROM loan_detail";
        $totalData = $this->db->query($countQuery)->getRow()->total;

        $baseQuery = "SELECT 
            loan_detail_book_id as id,
            loan_detail_book_title as title,
            loan_detail_book_publisher_name as publisher_name,
            loan_detail_book_publisher_address as publisher_address,
            loan_detail_book_publisher_phone as publisher_phone,
            loan_detail_book_publisher_email as publisher_email,
            loan_detail_book_publication_year as publication_year,
            loan_detail_book_isbn as isbn,
            loan_detail_book_author_name as author_name,
            loan_detail_book_author_biography as author_biography,
            COUNT(*) as borrow_count
            FROM loan_detail
            GROUP BY loan_detail_book_id
            ORDER BY borrow_count ASC
            LIMIT $offset, $limit";

        $books = $this->db->query($baseQuery)->getResult();

        $totalPages = ceil($totalData / $limit);
        $pagination = $this->generatePagination($page, $totalPages, $totalData);

        return $this->response->setJSON([
            'status' => 200,
            'message' => 'Berhasil mendapatkan data buku yang paling jarang dipinjam.',
            'result' => [
                'data' => $books,
                'pagination' => $pagination
            ]
        ]);
    }

    public function inactive_users()
    {
        $limit = $this->request->getVar('limit') ?? 10;
        $page = $this->request->getVar('page') ?? 1;
        $offset = ($page - 1) * $limit;

        $countQuery = "SELECT COUNT(DISTINCT loan_member_id) as total FROM loan";
        $totalData = $this->db->query($countQuery)->getRow()->total;

        $baseQuery = "SELECT 
            loan_member_id as id,
            loan_member_username as username,
            loan_member_email as email,
            loan_member_full_name as full_name,
            loan_member_address as address,
            COUNT(*) as loan_count
            FROM loan
            GROUP BY loan_member_id
            ORDER BY loan_count ASC
            LIMIT $offset, $limit";

        $users = $this->db->query($baseQuery)->getResult();

        $totalPages = ceil($totalData / $limit);
        $pagination = $this->generatePagination($page, $totalPages, $totalData);

        return $this->response->setJSON([
            'status' => 200,
            'message' => 'Berhasil mendapatkan data pengguna tidak aktif.',
            'result' => [
                'data' => $users,
                'pagination' => $pagination
            ]
        ]);
    }

    public function most_active_users()
    {
        $limit = $this->request->getVar('limit') ?? 10;
        $page = $this->request->getVar('page') ?? 1;
        $offset = ($page - 1) * $limit;

        $countQuery = "SELECT COUNT(DISTINCT loan_member_id) as total FROM loan";
        $totalData = $this->db->query($countQuery)->getRow()->total;

        $baseQuery = "SELECT 
            loan_member_id as id,
            loan_member_username as username,
            loan_member_email as email,
            loan_member_full_name as full_name,
            loan_member_address as address,
            COUNT(*) as loan_count
            FROM loan
            GROUP BY loan_member_id
            ORDER BY loan_count DESC
            LIMIT $offset, $limit";

        $users = $this->db->query($baseQuery)->getResult();

        $totalPages = ceil($totalData / $limit);
        $pagination = $this->generatePagination($page, $totalPages, $totalData);

        return $this->response->setJSON([
            'status' => 200,
            'message' => 'Berhasil mendapatkan data pengguna paling aktif.',
            'result' => [
                'data' => $users,
                'pagination' => $pagination
            ]
        ]);
    }

    public function broken_missing_books()
    {
        $limit = $this->request->getVar('limit') ?? 10;
        $page = $this->request->getVar('page') ?? 1;
        $offset = ($page - 1) * $limit;

        $countQuery = "SELECT COUNT(*) as total FROM loan_detail WHERE loan_detail_status IN ('Broken', 'Missing')";
        $totalData = $this->db->query($countQuery)->getRow()->total;

        $baseQuery = "SELECT 
            loan_detail_book_id as id,
            loan_detail_book_title as title,
            loan_detail_book_publisher_name as publisher_name,
            loan_detail_status as status,
            loan_detail_borrow_date as borrow_date,
            loan_detail_return_date as return_date
            FROM loan_detail
            WHERE loan_detail_status IN ('Broken', 'Missing')
            ORDER BY loan_detail_borrow_date DESC
            LIMIT $offset, $limit";

        $books = $this->db->query($baseQuery)->getResult();

        $totalPages = ceil($totalData / $limit);
        $pagination = $this->generatePagination($page, $totalPages, $totalData);

        return $this->response->setJSON([
            'status' => 200,
            'message' => 'Berhasil mendapatkan data buku rusak dan hilang.',
            'result' => [
                'data' => $books,
                'pagination' => $pagination
            ]
        ]);
    }

    public function detailed_member_activity()
    {
        $limit = $this->request->getVar('limit') ?? 10;
        $page = $this->request->getVar('page') ?? 1;
        $offset = ($page - 1) * $limit;

        $countQuery = "SELECT COUNT(*) as total FROM loan";
        $totalData = $this->db->query($countQuery)->getRow()->total;

        $baseQuery = "SELECT 
            l.loan_member_id as member_id,
            l.loan_member_username as username,
            l.loan_member_full_name as full_name,
            l.loan_date as loan_date,
            COUNT(ld.loan_detail_book_id) as total_books,
            SUM(CASE WHEN ld.loan_detail_status IN ('Broken', 'Missing') THEN 1 ELSE 0 END) as damaged_books
            FROM loan l, loan_detail ld
            WHERE l.loan_transaction_code = ld.loan_detail_loan_transaction_code
            GROUP BY l.loan_transaction_code
            ORDER BY l.loan_date DESC
            LIMIT $offset, $limit";

        $activities = $this->db->query($baseQuery)->getResult();

        $totalPages = ceil($totalData / $limit);
        $pagination = $this->generatePagination($page, $totalPages, $totalData);

        return $this->response->setJSON([
            'status' => 200,
            'message' => 'Berhasil mendapatkan data aktivitas detail anggota.',
            'result' => [
                'data' => $activities,
                'pagination' => $pagination
            ]
        ]);
    }

    public function count_books_status()
    {
        $db = Database::connect();

        // Books Status
        $booksStatusQuery = "
        SELECT 
            SUM(CASE WHEN loan_detail_status = 'Broken' THEN 1 ELSE 0 END) as damaged_count,
            SUM(CASE WHEN loan_detail_status = 'Missing' THEN 1 ELSE 0 END) as missing_count,
            SUM(CASE WHEN loan_detail_status = 'Borrowed' THEN 1 ELSE 0 END) as borrowed_count,
            (SELECT COUNT(*) FROM books) as total_books
        FROM loan_detail";
        $booksStatus = $db->query($booksStatusQuery)->getRow();

        // Loans Activity
        $loansActivityQuery = "
        SELECT 
            SUM(CASE WHEN DATE(loan_date) = CURDATE() THEN 1 ELSE 0 END) as today_loans,
            SUM(CASE WHEN YEARWEEK(loan_date, 1) = YEARWEEK(CURDATE(), 1) THEN 1 ELSE 0 END) as this_week_loans,
            SUM(CASE WHEN loan_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as last_30_days_loans,
            COUNT(*) as total_loans
        FROM loan";
        $loansActivity = $db->query($loansActivityQuery)->getRow();

        // Users Statistics
        $usersStatsQuery = "
        SELECT 
            (SELECT COUNT(*) FROM member) as total_members,
            (SELECT COUNT(*) FROM admin WHERE admin_id IN (SELECT admin_id FROM admin_token WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY))) as active_admins
        FROM dual";
        $usersStats = $db->query($usersStatsQuery)->getRow();

        // Library Resources
        $libraryResourcesQuery = "
        SELECT 
            (SELECT COUNT(*) FROM publisher) as total_publishers,
            (SELECT COUNT(*) FROM author) as total_authors
        FROM dual";
        $libraryResources = $db->query($libraryResourcesQuery)->getRow();

        // Calculate percentages and additional metrics
        $availableBooks = $booksStatus->total_books - $booksStatus->borrowed_count - $booksStatus->damaged_count - $booksStatus->missing_count;
        $borrowedPercentage = ($booksStatus->total_books > 0) ? ($booksStatus->borrowed_count / $booksStatus->total_books) * 100 : 0;
        $averageDailyLoans = ($loansActivity->last_30_days_loans / 30);

        // Prepare response data
        $result = [
            'data' => [
                'books_status' => [
                    'total' => (int) $booksStatus->total_books,
                    'available' => (int) $availableBooks,
                    'borrowed' => (int) $booksStatus->borrowed_count,
                    'damaged' => (int) $booksStatus->damaged_count,
                    'missing' => (int) $booksStatus->missing_count,
                    'borrowed_percentage' => round($borrowedPercentage, 2)
                ],
                'loans_activity' => [
                    'today' => (int) $loansActivity->today_loans,
                    'this_week' => (int) $loansActivity->this_week_loans,
                    'last_30_days' => (int) $loansActivity->last_30_days_loans,
                    'total' => (int) $loansActivity->total_loans,
                    'average_daily' => round($averageDailyLoans, 2)
                ],
                'users_statistics' => [
                    'total_members' => (int) $usersStats->total_members,
                    'active_admins' => (int) $usersStats->active_admins
                ],
                'library_resources' => [
                    'total_publishers' => (int) $libraryResources->total_publishers,
                    'total_authors' => (int) $libraryResources->total_authors
                ]
            ]
        ];

        return $this->respondWithSuccess('Behasil mengembalikan data statistik', $result);
    }

    private function generatePagination($currentPage, $totalPages, $totalData)
    {
        $pagination = [
            'total_data' => (int) $totalData,
            'jumlah_page' => (int) $totalPages,
            'prev' => $currentPage > 1 ? $currentPage - 1 : null,
            'page' => (int) $currentPage,
            'next' => $currentPage < $totalPages ? $currentPage + 1 : null,
            'start' => (int) $currentPage,
            'end' => (int) $currentPage,
            'detail' => []
        ];

        // Generate detail pages array
        $start = max(1, $currentPage - 1);
        $end = min($totalPages, $currentPage + 1);

        for ($i = $start; $i <= $end; $i++) {
            $pagination['detail'][] = $i;
        }

        return $pagination;
    }
}