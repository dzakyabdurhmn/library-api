<?php

namespace App\Controllers;

use Config\Database;

class ReportController extends AuthorizationController
{
    private $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }



    public function most_borrowed_books()
    {
        $db = \Config\Database::connect();

        $limit = (int) ($this->request->getVar('limit') ?? 10);
        $page = (int) ($this->request->getVar('page') ?? 1);
        $search = $this->request->getVar('search');
        $sort = $this->request->getVar('sort') ?? '';
        $filters = $this->request->getVar('filter') ?? [];
        $enablePagination = filter_var($this->request->getVar('pagination'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
        $offset = ($page - 1) * $limit;

        // Count query for total data
        $countQuery = "SELECT COUNT(DISTINCT loan_detail_book_id) as total FROM loan_detail";
        $totalData = $db->query($countQuery)->getRow()->total;

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
        FROM loan_detail";

        // Filtering
        $conditions = [];
        $params = [];

        if ($search) {
            $conditions[] = "(loan_detail_book_title LIKE ? OR loan_detail_book_isbn LIKE ?)";
            $searchTerm = "%" . $search . "%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // Filter mapping
        $filterMapping = [
            'publisher_name' => 'loan_detail_book_publisher_name',
            'publisher_address' => 'loan_detail_book_publisher_address',
            'publisher_phone' => 'loan_detail_book_publisher_phone',
            'publisher_email' => 'loan_detail_book_publisher_email',
            'author_name' => 'loan_detail_book_author_name',
            'author_biography' => 'loan_detail_book_author_biography',
            'isbn' => 'loan_detail_book_isbn',
            'title' => 'loan_detail_book_title',
            'year' => 'loan_detail_book_publication_year',
        ];

        foreach ($filters as $key => $value) {
            if (!empty($value) && array_key_exists($key, $filterMapping)) {
                $conditions[] = "{$filterMapping[$key]} = ?";
                $params[] = $value;
            }
        }

        if (count($conditions) > 0) {
            $baseQuery .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $baseQuery .= " GROUP BY loan_detail_book_id";

        // Sorting
        if (!empty($sort)) {
            $sortDirection = $sort[0] === '-' ? 'DESC' : 'ASC';
            $sortField = ltrim($sort, '-');
            $baseQuery .= " ORDER BY $sortField $sortDirection";
        } else {
            $baseQuery .= " ORDER BY borrow_count DESC";
        }

        // Pagination
        if ($enablePagination) {
            $baseQuery .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
        }

        // Execute query
        try {
            $books = $db->query($baseQuery, $params)->getResult();

            // Calculate pagination
            $pagination = new \stdClass();
            if ($enablePagination) {
                $totalPages = ceil($totalData / $limit);
                $pagination = [
                    'total_data' => (int) $totalData,
                    'jumlah_page' => (int) $totalPages,
                    'prev' => ($page > 1) ? $page - 1 : null,
                    'page' => (int) $page,
                    'next' => ($page < $totalPages) ? $page + 1 : null,
                    'start' => ($page - 1) * $limit + 1,
                    'end' => min($page * $limit, $totalData),
                    'detail' => range(max(1, $page - 2), min($totalPages, $page + 2)),
                ];
            }

            return $this->respondWithSuccess('Berhasil mendapatkan data buku yang paling sering dipinjam.', [
                'data' => $books,
                'pagination' => $pagination
            ]);
        } catch (DatabaseException $e) {
            return $this->respondWithError('Terdapat kesalahan di sisi server: ' . $e->getMessage());
        }
    }

    public function least_borrowed_books()
    {
        $db = \Config\Database::connect();

        $limit = (int) ($this->request->getVar('limit') ?? 10);
        $page = (int) ($this->request->getVar('page') ?? 1);
        $search = $this->request->getVar('search');
        $sort = $this->request->getVar('sort') ?? '';
        $filters = $this->request->getVar('filter') ?? [];
        $enablePagination = filter_var($this->request->getVar('pagination'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
        $offset = ($page - 1) * $limit;

        // Count query for total data
        $countQuery = "SELECT COUNT(DISTINCT loan_detail_book_id) as total FROM loan_detail";
        $totalData = $db->query($countQuery)->getRow()->total;

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
        FROM loan_detail";

        // Filtering
        $conditions = [];
        $params = [];

        if ($search) {
            $conditions[] = "(loan_detail_book_title LIKE ? OR loan_detail_book_isbn LIKE ?)";
            $searchTerm = "%" . $search . "%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // Filter mapping
        $filterMapping = [
            'publisher_name' => 'loan_detail_book_publisher_name',
            'publisher_address' => 'loan_detail_book_publisher_address',
            'publisher_phone' => 'loan_detail_book_publisher_phone',
            'publisher_email' => 'loan_detail_book_publisher_email',
            'author_name' => 'loan_detail_book_author_name',
            'author_biography' => 'loan_detail_book_author_biography',
            'isbn' => 'loan_detail_book_isbn',
            'title' => 'loan_detail_book_title',
            'year' => 'loan_detail_book_publication_year',
        ];

        foreach ($filters as $key => $value) {
            if (!empty($value) && array_key_exists($key, $filterMapping)) {
                $conditions[] = "{$filterMapping[$key]} = ?";
                $params[] = $value;
            }
        }

        if (count($conditions) > 0) {
            $baseQuery .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $baseQuery .= " GROUP BY loan_detail_book_id";

        // Sorting
        if (!empty($sort)) {
            $sortDirection = $sort[0] === '-' ? 'DESC' : 'ASC';
            $sortField = ltrim($sort, '-');
            $baseQuery .= " ORDER BY $sortField $sortDirection";
        } else {
            $baseQuery .= " ORDER BY borrow_count ASC";
        }

        // Pagination
        if ($enablePagination) {
            $baseQuery .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
        }

        // Execute query
        try {
            $books = $db->query($baseQuery, $params)->getResult();

            // Calculate pagination
            $pagination = new \stdClass();
            if ($enablePagination) {
                $totalPages = ceil($totalData / $limit);
                $pagination = [
                    'total_data' => (int) $totalData,
                    'jumlah_page' => (int) $totalPages,
                    'prev' => ($page > 1) ? $page - 1 : null,
                    'page' => (int) $page,
                    'next' => ($page < $totalPages) ? $page + 1 : null,
                    'start' => ($page - 1) * $limit + 1,
                    'end' => min($page * $limit, $totalData),
                    'detail' => range(max(1, $page - 2), min($totalPages, $page + 2)),
                ];
            }

            return $this->response->setJSON([
                'status' => 200,
                'message' => 'Berhasil mendapatkan data buku yang paling jarang dipinjam.',
                'result' => [
                    'data' => $books,
                    'pagination' => $pagination,
                ]
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 500,
                'message' => 'Gagal mendapatkan data buku yang paling jarang dipinjam.',
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function inactive_users()
    {
        $db = \Config\Database::connect();

        $limit = (int) ($this->request->getVar('limit') ?? 10);
        $page = (int) ($this->request->getVar('page') ?? 1);
        $search = $this->request->getVar('search');
        $sort = $this->request->getVar('sort') ?? '';
        $filters = $this->request->getVar('filter') ?? [];
        $enablePagination = filter_var($this->request->getVar('pagination'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
        $offset = ($page - 1) * $limit;

        // Count query for total data
        $countQuery = "SELECT COUNT(DISTINCT loan_member_id) as total FROM loan";
        $totalData = $db->query($countQuery)->getRow()->total;

        // Main query
        $baseQuery = "SELECT 
        loan_member_id as id,
        loan_member_username as username,
        loan_member_email as email,
        loan_member_full_name as full_name,
        loan_member_address as address,
        COUNT(*) as loan_count
        FROM loan";

        // Filtering
        $conditions = [];
        $params = [];

        if ($search) {
            $conditions[] = "(loan_member_username LIKE ? OR loan_member_email LIKE ? OR loan_member_full_name LIKE ?)";
            $searchTerm = "%" . $search . "%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // Filter mapping
        $filterMapping = [
            'username' => 'loan_member_username',
            'email' => 'loan_member_email',
            'full_name' => 'loan_member_full_name',
            'address' => 'loan_member_address',
        ];

        foreach ($filters as $key => $value) {
            if (!empty($value) && array_key_exists($key, $filterMapping)) {
                $conditions[] = "{$filterMapping[$key]} = ?";
                $params[] = $value;
            }
        }

        if (count($conditions) > 0) {
            $baseQuery .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $baseQuery .= " GROUP BY loan_member_id";

        // Sorting
        if (!empty($sort)) {
            $sortDirection = $sort[0] === '-' ? 'DESC' : 'ASC';
            $sortField = ltrim($sort, '-');
            $baseQuery .= " ORDER BY $sortField $sortDirection";
        } else {
            $baseQuery .= " ORDER BY loan_count ASC";
        }

        // Pagination
        if ($enablePagination) {
            $baseQuery .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
        }

        // Execute query
        try {
            $users = $db->query($baseQuery, $params)->getResult();

            // Calculate pagination
            $pagination = new \stdClass();
            if ($enablePagination) {
                $totalPages = ceil($totalData / $limit);
                $pagination = [
                    'total_data' => (int) $totalData,
                    'jumlah_page' => (int) $totalPages,
                    'prev' => ($page > 1) ? $page - 1 : null,
                    'page' => (int) $page,
                    'next' => ($page < $totalPages) ? $page + 1 : null,
                    'start' => ($page - 1) * $limit + 1,
                    'end' => min($page * $limit, $totalData),
                    'detail' => range(max(1, $page - 2), min($totalPages, $page + 2)),
                ];
            }

            return $this->response->setJSON([
                'status' => 200,
                'message' => 'Berhasil mendapatkan data pengguna tidak aktif.',
                'result' => [
                    'data' => $users,
                    'pagination' => $pagination,
                ]
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 500,
                'message' => 'Gagal mendapatkan data pengguna tidak aktif.',
                'error' => $e->getMessage(),
            ]);
        }
    }
    // public function most_active_users()
    // {
    //     $db = \Config\Database::connect();

    //     $limit = (int) ($this->request->getVar('limit') ?? 10);
    //     $page = (int) ($this->request->getVar('page') ?? 1);
    //     $search = $this->request->getVar('search');
    //     $sort = $this->request->getVar('sort') ?? '';
    //     $filters = $this->request->getVar('filter') ?? [];
    //     $enablePagination = filter_var($this->request->getVar('pagination'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
    //     $offset = ($page - 1) * $limit;

    //     // Count query for total data
    //     $countQuery = "SELECT COUNT(DISTINCT loan_member_id) as total FROM loan";
    //     $totalData = $db->query($countQuery)->getRow()->total;

    //     // Main query
    //     $baseQuery = "SELECT 
    //     loan_member_id as id,
    //     loan_member_username as username,
    //     loan_member_email as email,
    //     loan_member_full_name as full_name,
    //     loan_member_address as address,
    //     COUNT(*) as loan_count
    //     FROM loan";

    //     // Filtering
    //     $conditions = [];
    //     $params = [];

    //     if ($search) {
    //         $conditions[] = "(loan_member_username LIKE ? OR loan_member_email LIKE ? OR loan_member_full_name LIKE ?)";
    //         $searchTerm = "%" . $search . "%";
    //         $params[] = $searchTerm;
    //         $params[] = $searchTerm;
    //         $params[] = $searchTerm;
    //     }

    //     // Filter mapping
    //     $filterMapping = [
    //         'username' => 'loan_member_username',
    //         'email' => 'loan_member_email',
    //         'full_name' => 'loan_member_full_name',
    //         'address' => 'loan_member_address',
    //     ];

    //     foreach ($filters as $key => $value) {
    //         if (!empty($value) && array_key_exists($key, $filterMapping)) {
    //             $conditions[] = "{$filterMapping[$key]} = ?";
    //             $params[] = $value;
    //         }
    //     }

    //     if (count($conditions) > 0) {
    //         $baseQuery .= ' WHERE ' . implode(' AND ', $conditions);
    //     }

    //     $baseQuery .= " GROUP BY loan_member_id";

    //     // Sorting
    //     if (!empty($sort)) {
    //         $sortDirection = $sort[0] === '-' ? 'DESC' : 'ASC';
    //         $sortField = ltrim($sort, '-');
    //         $baseQuery .= " ORDER BY $sortField $sortDirection";
    //     } else {
    //         $baseQuery .= " ORDER BY loan_count DESC";
    //     }

    //     // Pagination
    //     if ($enablePagination) {
    //         $baseQuery .= " LIMIT ? OFFSET ?";
    //         $params[] = $limit;
    //         $params[] = $offset;
    //     }

    //     // Execute query
    //     try {
    //         $users = $db->query($baseQuery, $params)->getResult();

    //         // Calculate pagination
    //         $pagination = new \stdClass();
    //         if ($enablePagination) {
    //             $totalPages = ceil($totalData / $limit);
    //             $pagination = [
    //                 'total_data' => (int) $totalData,
    //                 'jumlah_page' => (int) $totalPages,
    //                 'prev' => ($page > 1) ? $page - 1 : null,
    //                 'page' => (int) $page,
    //                 'next' => ($page < $totalPages) ? $page + 1 : null,
    //                 'start' => ($page - 1) * $limit + 1,
    //                 'end' => min($page * $limit, $totalData),
    //                 'detail' => range(max(1, $page - 2), min($totalPages, $page + 2)),
    //             ];
    //         }

    //         return $this->response->setJSON([
    //             'status' => 200,
    //             'message' => 'Berhasil mendapatkan data pengguna paling aktif.',
    //             'result' => [
    //                 'data' => $users,
    //                 'pagination' => $pagination,
    //             ]
    //         ]);
    //     } catch (\Exception $e) {
    //         return $this->response->setJSON([
    //             'status' => 500,
    //             'message' => 'Gagal mendapatkan data pengguna paling aktif.',
    //             'error' => $e->getMessage(),
    //         ]);
    //     }
    // }

    public function broken_missing_books()
    {
        $db = \Config\Database::connect();

        $limit = (int) ($this->request->getVar('limit') ?? 10);
        $page = (int) ($this->request->getVar('page') ?? 1);
        $search = $this->request->getVar('search');
        $sort = $this->request->getVar('sort') ?? '';
        $filters = $this->request->getVar('filter') ?? [];
        $enablePagination = filter_var($this->request->getVar('pagination'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
        $offset = ($page - 1) * $limit;

        // Count query for total data
        $countQuery = "SELECT COUNT(*) as total FROM loan_detail WHERE loan_detail_status IN ('Broken', 'Missing')";
        $totalData = $db->query($countQuery)->getRow()->total;

        // Main query
        $baseQuery = "SELECT 
        loan_detail_book_id as id,
        loan_detail_book_title as title,
        loan_detail_book_publisher_name as publisher_name,
        loan_detail_status as status,
        loan_detail_borrow_date as borrow_date,
        loan_detail_return_date as return_date
        FROM loan_detail
        WHERE loan_detail_status IN ('Broken', 'Missing')";

        // Filtering
        $conditions = [];
        $params = [];

        if ($search) {
            $conditions[] = "(loan_detail_book_title LIKE ? OR loan_detail_book_publisher_name LIKE ?)";
            $searchTerm = "%" . $search . "%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // Filter mapping
        $filterMapping = [
            'title' => 'loan_detail_book_title',
            'publisher_name' => 'loan_detail_book_publisher_name',
            'status' => 'loan_detail_status',
        ];

        foreach ($filters as $key => $value) {
            if (!empty($value) && array_key_exists($key, $filterMapping)) {
                $conditions[] = "{$filterMapping[$key]} = ?";
                $params[] = $value;
            }
        }

        if (count($conditions) > 0) {
            $baseQuery .= ' AND ' . implode(' AND ', $conditions);
        }

        // Sorting
        if (!empty($sort)) {
            $sortDirection = $sort[0] === '-' ? 'DESC' : 'ASC';
            $sortField = ltrim($sort, '-');
            $baseQuery .= " ORDER BY $sortField $sortDirection";
        } else {
            $baseQuery .= " ORDER BY loan_detail_borrow_date DESC";
        }

        // Pagination
        if ($enablePagination) {
            $baseQuery .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
        }

        // Execute query
        try {
            $books = $db->query($baseQuery, $params)->getResult();

            // Calculate pagination
            $pagination = new \stdClass();
            if ($enablePagination) {
                $totalPages = ceil($totalData / $limit);
                $pagination = [
                    'total_data' => (int) $totalData,
                    'jumlah_page' => (int) $totalPages,
                    'prev' => ($page > 1) ? $page - 1 : null,
                    'page' => (int) $page,
                    'next' => ($page < $totalPages) ? $page + 1 : null,
                    'start' => ($page - 1) * $limit + 1,
                    'end' => min($page * $limit, $totalData),
                    'detail' => range(max(1, $page - 2), min($totalPages, $page + 2)),
                ];
            }

            return $this->response->setJSON([
                'status' => 200,
                'message' => 'Berhasil mendapatkan data buku rusak dan hilang.',
                'result' => [
                    'data' => $books,
                    'pagination' => $pagination,
                ]
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 500,
                'message' => 'Gagal mendapatkan data buku rusak dan hilang.',
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function detailed_member_activity()
    {
    $db = \Config\Database::connect();

    $limit = (int) ($this->request->getVar('limit') ?? 10);
    $page = (int) ($this->request->getVar('page') ?? 1);
    $search = $this->request->getVar('search');
    $sort = $this->request->getVar('sort') ?? '';
    $filters = $this->request->getVar('filter') ?? [];
    $enablePagination = filter_var($this->request->getVar('pagination'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
    $offset = ($page - 1) * $limit;

    // Count query for total data
    $countQuery = "SELECT COUNT(DISTINCT l.loan_member_id) as total FROM loan l JOIN loan_detail ld ON l.loan_transaction_code = ld.loan_detail_loan_transaction_code";
    $totalData = $db->query($countQuery)->getRow()->total;

    // Main query
    $baseQuery = "SELECT 
        l.loan_member_id as member_id,
        l.loan_member_username as username,
        l.loan_member_full_name as full_name,
        l.loan_date as loan_date,
        COUNT(ld.loan_detail_book_id) as total_books,
        SUM(CASE WHEN ld.loan_detail_status IN ('Broken', 'Missing') THEN 1 ELSE 0 END) as damaged_books
        FROM loan l
        JOIN loan_detail ld ON l.loan_transaction_code = ld.loan_detail_loan_transaction_code";

    // Filtering
    $conditions = [];
    $params = [];

    if ($search) {
        $conditions[] = "(l.loan_member_username LIKE ? OR l.loan_member_full_name LIKE ?)";
        $searchTerm = "%" . $search . "%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    // Filter mapping
    $filterMapping = [
        'member_id' => 'l.loan_member_id',
        'username' => 'l.loan_member_username',
        'full_name' => 'l.loan_member_full_name',
    ];

    foreach ($filters as $key => $value) {
        if (!empty($value) && array_key_exists($key, $filterMapping)) {
            $conditions[] = "{$filterMapping[$key]} = ?";
            $params[] = $value;
        }
    }

    if (count($conditions) > 0) {
        $baseQuery .= ' WHERE ' . implode(' AND ', $conditions);
    }

    // Grouping and ordering
    $baseQuery .= " GROUP BY l.loan_member_id";

    // Sorting
    if (!empty($sort)) {
        $sortDirection = $sort[0] === '-' ? 'DESC' : 'ASC';
        $sortField = ltrim($sort, '-');
        $baseQuery .= " ORDER BY $sortField $sortDirection";
    } else {
        $baseQuery .= " ORDER BY l.loan_date DESC";
    }

    // Pagination
    if ($enablePagination) {
        $baseQuery .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
    }

    // Execute query
    try {
        $activities = $db->query($baseQuery, $params)->getResult();

        // Calculate pagination
        $pagination = new \stdClass();
        if ($enablePagination) {
            $totalPages = ceil($totalData / $limit);
            $pagination = [
                'total_data' => (int) $totalData,
                'jumlah_page' => (int) $totalPages,
                'prev' => ($page > 1) ? $page - 1 : null,
                'page' => (int) $page,
                'next' => ($page < $totalPages) ? $page + 1 : null,
                'start' => ($page - 1) * $limit + 1,
                'end' => min($page * $limit, $totalData),
                'detail' => range(max(1, $page - 2), min($totalPages, $page + 2)),
            ];
        }

        return $this->response->setJSON([
            'status' => 200,
            'message' => 'Berhasil mendapatkan data aktivitas detail anggota.',
            'result' => [
                'data' => $activities,
                'pagination' => $pagination,
            ]
        ]);
    } catch (\Exception $e) {
        return $this->response->setJSON([
            'status' => 500,
 'message' => 'Gagal mendapatkan data aktivitas detail anggota.',
            'error' => $e->getMessage(),
        ]);
    }
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


}