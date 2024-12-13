<?php

namespace App\Controllers;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ReportController extends AuthorizationController
{


    public function most_borrowed_books()
    {

        $limit = (int) ($this->request->getVar("limit") ?? 10);
        $page = (int) ($this->request->getVar("page") ?? 1);
        $search = $this->request->getVar("search");
        $sort = $this->request->getVar("sort") ?? "";
        $filters = $this->request->getVar("filter") ?? [];
        $enablePagination =
            filter_var(
                $this->request->getVar("pagination"),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            ) ?? true;
        $offset = ($page - 1) * $limit;

        // Count total rows
        $countQuery =
            "SELECT COUNT(DISTINCT loan_detail_book_id) as total FROM loan_detail";
        $totalData = $this->db->query($countQuery)->getRow()->total;

        // Start building the base query
        $baseQuery = "
        SELECT 
            loan_detail_book_id AS id,
            loan_detail_book_title AS title,
            loan_detail_book_publisher_name AS publisher_name,
            loan_detail_book_publisher_address AS publisher_address,
            loan_detail_book_publisher_phone AS publisher_phone,
            loan_detail_book_publisher_email AS publisher_email,
            loan_detail_book_publication_year AS publication_year,
            loan_detail_book_isbn AS isbn,
            loan_detail_book_author_name AS author_name,
            loan_detail_book_author_biography AS author_biography,
            COUNT(*) AS borrow_count
        FROM loan_detail
    ";
        // Adding filters
        $conditions = [];
        $params = [];

        // Search filter
        if ($search) {
            $conditions[] =
                "(loan_detail_book_title LIKE ? OR loan_detail_book_isbn LIKE ?)";
            $searchTerm = "%" . $search . "%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // Filter mappings
        $filterMapping = [
            "publisher_name" => "loan_detail_book_publisher_name",
            "publisher_address" => "loan_detail_book_publisher_address",
            "publisher_phone" => "loan_detail_book_publisher_phone",
            "publisher_email" => "loan_detail_book_publisher_email",
            "author_name" => "loan_detail_book_author_name",
            "author_biography" => "loan_detail_book_author_biography",
            "isbn" => "loan_detail_book_isbn",
            "title" => "loan_detail_book_title",
            "year" => "loan_detail_book_publication_year",
        ];

        foreach ($filters as $key => $value) {
            if (!empty($value) && array_key_exists($key, $filterMapping)) {
                $conditions[] = "{$filterMapping[$key]} = ?";
                $params[] = $value;
            }
        }

        if (count($conditions) > 0) {
            $baseQuery .= " WHERE " . implode(" AND ", $conditions);
        }

        // Grouping and Sorting
        $baseQuery .= "
        GROUP BY 
            loan_detail_book_id, 
            loan_detail_book_title, 
            loan_detail_book_publisher_name, 
            loan_detail_book_publisher_address, 
            loan_detail_book_publisher_phone, 
            loan_detail_book_publisher_email, 
            loan_detail_book_publication_year, 
            loan_detail_book_isbn, 
            loan_detail_book_author_name, 
            loan_detail_book_author_biography
    ";

        // Sorting
        if (!empty($sort)) {
            // Safeguard for sort field
            $validSortFields = ["publisher_name", "borrow_count"]; // List of allowed fields
            $sortDirection = $sort[0] === "-" ? "DESC" : "ASC";
            $sortField = ltrim($sort, "-");

            // Check if sort field is valid
            if (in_array($sortField, $validSortFields)) {
                $baseQuery .= " ORDER BY $sortField $sortDirection";
            }
        } else {
            // Default sorting by borrow count
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
            $books = $this->db->query($baseQuery, $params)->getResult();

            // Pagination calculation
            $pagination = new \stdClass();
            if ($enablePagination) {
                $totalPages = ceil($totalData / $limit);
                $pagination = [
                    "total_data" => (int) $totalData,
                    "jumlah_page" => (int) $totalPages,
                    "prev" => $page > 1 ? $page - 1 : null,
                    "page" => (int) $page,
                    "next" => $page < $totalPages ? $page + 1 : null,
                    "start" => ($page - 1) * $limit + 1,
                    "end" => min($page * $limit, $totalData),
                    "detail" => range(
                        max(1, $page - 2),
                        min($totalPages, $page + 2)
                    ),
                ];
            }

            return $this->respondWithSuccess(
                "Berhasil mendapatkan data buku yang paling sering dipinjam.",
                [
                    "data" => $books,
                    "pagination" => $pagination,
                ]
            );
        } catch (DatabaseException $e) {
            return $this->respondWithError(
                "Terdapat kesalahan di sisi server: " . $e->getMessage()
            );
        }
    }

    public function least_borrowed_books()
    {

        $limit = (int) ($this->request->getVar("limit") ?? 10);
        $page = (int) ($this->request->getVar("page") ?? 1);
        $search = $this->request->getVar("search");
        $sort = $this->request->getVar("sort") ?? "";
        $filters = $this->request->getVar("filter") ?? [];
        $enablePagination =
            filter_var(
                $this->request->getVar("pagination"),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            ) ?? true;
        $offset = ($page - 1) * $limit;

        // Filter mapping
        $filterMapping = [
            "publisher_name" => "loan_detail_book_publisher_name",
            "publisher_address" => "loan_detail_book_publisher_address",
            "publisher_phone" => "loan_detail_book_publisher_phone",
            "publisher_email" => "loan_detail_book_publisher_email",
            "author_name" => "loan_detail_book_author_name",
            "author_biography" => "loan_detail_book_author_biography",
            "isbn" => "loan_detail_book_isbn",
            "title" => "loan_detail_book_title",
            "year" => "loan_detail_book_publication_year",
        ];

        // Count query for filtered data
        $countQuery =
            "SELECT COUNT(DISTINCT loan_detail_book_id) as total FROM loan_detail";
        $conditions = [];
        $params = [];

        // Search filter
        if ($search) {
            $conditions[] =
                "(loan_detail_book_title LIKE ? OR loan_detail_book_isbn LIKE ?)";
            $searchTerm = "%" . $search . "%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // Apply filters
        foreach ($filters as $key => $value) {
            if (!empty($value) && array_key_exists($key, $filterMapping)) {
                $conditions[] = "{$filterMapping[$key]} = ?";
                $params[] = $value;
            }
        }

        // Add conditions to count query
        if (count($conditions) > 0) {
            $countQuery .= " WHERE " . implode(" AND ", $conditions);
        }

        // Execute count query
        $totalData = $this->db->query($countQuery, $params)->getRow()->total;

        // Main query
        $baseQuery = "SELECT 
        loan_detail_book_id as id,
        MAX(loan_detail_book_title) as title,
        MAX(loan_detail_book_publisher_name) as publisher_name,
        MAX(loan_detail_book_publisher_address) as publisher_address,
        MAX(loan_detail_book_publisher_phone) as publisher_phone,
        MAX(loan_detail_book_publisher_email) as publisher_email,
        MAX(loan_detail_book_publication_year) as publication_year,
        MAX(loan_detail_book_isbn) as isbn,
        MAX(loan_detail_book_author_name) as author_name,
        MAX(loan_detail_book_author_biography) as author_biography,
        COUNT(*) as borrow_count
    FROM loan_detail";

        // Add conditions to main query
        if (count($conditions) > 0) {
            $baseQuery .= " WHERE " . implode(" AND ", $conditions);
        }

        $baseQuery .= " GROUP BY loan_detail_book_id";

        // Sorting
        $allowedSortFields = array_merge(array_keys($filterMapping), [
            "borrow_count",
        ]);
        $sortField = "";
        $sortDirection = "ASC"; // Default sorting direction

        if (!empty($sort)) {
            $sortField = ltrim($sort, "-");
            $sortDirection = $sort[0] === "-" ? "DESC" : "ASC";
        }

        if (in_array($sortField, $allowedSortFields)) {
            $baseQuery .= " ORDER BY $sortField $sortDirection";
        } else {
            $baseQuery .= " ORDER BY borrow_count ASC"; // Default sorting
        }

        // Pagination
        if ($enablePagination) {
            $baseQuery .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
        }

        // Execute query
        try {
            $books = $this->db->query($baseQuery, $params)->getResult();

            // Calculate pagination
            $pagination = new \stdClass();
            if ($enablePagination) {
                $totalPages = ceil($totalData / $limit);
                $pagination = [
                    "total_data" => (int) $totalData,
                    "jumlah_page" => (int) $totalPages,
                    "prev" => $page > 1 ? $page - 1 : null,
                    "page" => (int) $page,
                    "next" => $page < $totalPages ? $page + 1 : null,
                    "start" => ($page - 1) * $limit + 1,
                    "end" => min($page * $limit, $totalData),
                    "detail" => range(
                        max(1, $page - 2),
                        min($totalPages, $page + 2)
                    ),
                ];
            }

            return $this->response->setJSON([
                "status" => 200,
                "message" =>
                    "Berhasil mendapatkan data buku yang paling jarang dipinjam.",
                "result" => [
                    "data" => $books,
                    "pagination" => $pagination,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                "status" => 500,
                "message" =>
                    "Gagal mendapatkan data buku yang paling jarang dipinjam.",
                "error" => $e->getMessage(),
            ]);
        }
    }

    public function inactive_users()
    {

        $limit = (int) ($this->request->getVar("limit") ?? 10);
        $page = (int) ($this->request->getVar("page") ?? 1);
        $search = $this->request->getVar("search");
        $sort = $this->request->getVar("sort") ?? "";
        $filters = $this->request->getVar("filter") ?? [];
        $enablePagination =
            filter_var(
                $this->request->getVar("pagination"),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            ) ?? true;
        $offset = ($page - 1) * $limit;

        // Count query for total data
        $countQuery =
            "SELECT COUNT(DISTINCT loan_member_id) as total FROM loan";
        $totalData = $this->db->query($countQuery)->getRow()->total;

        // Main query
        $baseQuery = "SELECT 
        loan_member_id as id,
        MAX(loan_member_institution) as institution,
        MAX(loan_member_email) as email,
        MAX(loan_member_full_name) as full_name,
        MAX(loan_member_address) as address,
        COUNT(*) as loan_count
    FROM loan";

        // Filtering
        $conditions = [];
        $params = [];

        if ($search) {
            $conditions[] =
                "(loan_member_institution LIKE ? OR loan_member_email LIKE ? OR loan_member_full_name LIKE ?)";
            $searchTerm = "%" . $search . "%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // Filter mapping
        $filterMapping = [
            "institution" => "loan_member_institution",
            "email" => "loan_member_email",
            "full_name" => "loan_member_full_name",
            "address" => "loan_member_address",
        ];

        foreach ($filters as $key => $value) {
            if (!empty($value) && array_key_exists($key, $filterMapping)) {
                $conditions[] = "{$filterMapping[$key]} = ?";
                $params[] = $value;
            }
        }

        if (count($conditions) > 0) {
            $baseQuery .= " WHERE " . implode(" AND ", $conditions);
        }

        $baseQuery .= " GROUP BY loan_member_id";

        // Sorting
        if (!empty($sort)) {
            $sortDirection = $sort[0] === "-" ? "DESC" : "ASC";
            $sortField = ltrim($sort, "-");
            // Pastikan sortField adalah kolom yang valid
            if (
                in_array($sortField, [
                    "loan_member_institution",
                    "loan_member_email",
                    "loan_member_full_name",
                    "loan_member_address",
                    "loan_count",
                ])
            ) {
                $baseQuery .= " ORDER BY $sortField $sortDirection";
            } else {
                $baseQuery .= " ORDER BY loan_count DESC"; // Default sorting by loan_count descending
            }
        } else {
            $baseQuery .= " ORDER BY loan_count DESC"; // Default sorting by loan_count descending
        }

        // Pagination
        if ($enablePagination) {
            $baseQuery .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
        }

        // Execute query
        try {
            $users = $this->db->query($baseQuery, $params)->getResult();

            // Calculate pagination
            $pagination = new \stdClass();
            if ($enablePagination) {
                $totalPages = ceil($totalData / $limit);
                $pagination = [
                    "total_data" => (int) $totalData,
                    "jumlah_page" => (int) $totalPages,
                    "prev" => $page > 1 ? $page - 1 : null,
                    "page" => (int) $page,
                    "next" => $page < $totalPages ? $page + 1 : null,
                    "start" => ($page - 1) * $limit + 1,
                    "end" => min($page * $limit, $totalData),
                    "detail" => range(
                        max(1, $page - 2),
                        min($totalPages, $page + 2)
                    ),
                ];
            }

            return $this->response->setJSON([
                "status" => 200,
                "message" => "Berhasil mendapatkan data pengguna tidak aktif.",
                "result" => [
                    "data" => $users,
                    "pagination" => $pagination,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                "status" => 500,
                "message" => "Gagal mendapatkan data pengguna tidak aktif.",
                "error" => $e->getMessage(),
            ]);
        }
    }

    public function broken_missing_books()
    {

        $limit = (int) ($this->request->getVar("limit") ?? 10);
        $page = (int) ($this->request->getVar("page") ?? 1);
        $search = $this->request->getVar("search");
        $sort = $this->request->getVar("sort") ?? "";
        $filters = $this->request->getVar("filter") ?? [];
        $enablePagination =
            filter_var(
                $this->request->getVar("pagination"),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            ) ?? true;
        $offset = ($page - 1) * $limit;

        // Count query for total data
        $countQuery = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN loan_detail_status = 'Broken' THEN 1 ELSE 0 END) as total_broken,
        SUM(CASE WHEN loan_detail_status = 'Missing' THEN 1 ELSE 0 END) as total_missing
    FROM loan_detail 
    WHERE loan_detail_status IN ('Broken', 'Missing')";

        $totalData = $this->db->query($countQuery)->getRow();

        // Count per book query
        $countPerBookQuery = "SELECT 
        loan_detail_book_id as book_id,
        loan_detail_book_title as book_title,
        MAX(loan_detail_status) as status,
        SUM(CASE WHEN loan_detail_status = 'Broken' THEN 1 ELSE 0 END) as broken,
        SUM(CASE WHEN loan_detail_status = 'Missing' THEN 1 ELSE 0 END) as missing
    FROM loan_detail 
    WHERE loan_detail_status IN ('Broken', 'Missing')
    GROUP BY loan_detail_book_id, loan_detail_book_title";

        $countPerBook = $this->db->query($countPerBookQuery)->getResult();

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
            $conditions[] =
                "(loan_detail_book_title LIKE ? OR loan_detail_book_publisher_name LIKE ?)";
            $searchTerm = "%" . $search . "%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // Filter mapping
        $filterMapping = [
            "title" => "loan_detail_book_title",
            "publisher_name" => "loan_detail_book_publisher_name",
            "status" => "loan_detail_status",
        ];

        foreach ($filters as $key => $value) {
            if (!empty($value) && array_key_exists($key, $filterMapping)) {
                $conditions[] = "{$filterMapping[$key]} = ?";
                $params[] = $value;
            }
        }

        if (count($conditions) > 0) {
            $baseQuery .= " AND " . implode(" AND ", $conditions);
        }

        // Sorting
        if (!empty($sort)) {
            $sortDirection = $sort[0] === "-" ? "DESC" : "ASC";
            $sortField = ltrim($sort, "-");
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
            $books = $this->db->query($baseQuery, $params)->getResult();

            // Calculate pagination
            $pagination = new \stdClass();
            if ($enablePagination) {
                $totalPages = ceil($totalData->total / $limit);
                $pagination = [
                    "total_data" => (int) $totalData->total,
                    "total_broken" => (int) $totalData->total_broken,
                    "total_missing" => (int) $totalData->total_missing,
                    "jumlah_page" => (int) $totalPages,
                    "prev" => $page > 1 ? $page - 1 : null,
                    "page" => (int) $page,
                    "next" => $page < $totalPages ? $page + 1 : null,
                    "start" => ($page - 1) * $limit + 1,
                    "end" => min($page * $limit, $totalData->total),
                    "detail" => range(
                        max(1, $page - 2),
                        min($totalPages, $page + 2)
                    ),
                ];
            }

            return $this->response->setJSON([
                "status" => 200,
                "message" => "Berhasil mendapatkan data buku rusak dan hilang.",
                "result" => [
                    "total" => [
                        "total_books" => $totalData->total,
                        "total_broken" => $totalData->total_broken,
                        "total_missing" => $totalData->total_missing,
                    ],
                    "detail" => $countPerBook,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                "status" => 500,
                "message" => "Gagal mendapatkan data buku rusak dan hilang.",
                "error" => $e->getMessage(),
            ]);
        }
    }
    public function detailed_member_activity()
    {

        $limit = (int) ($this->request->getVar("limit") ?? 10);
        $page = (int) ($this->request->getVar("page") ?? 1);
        $search = $this->request->getVar("search");
        $sort = $this->request->getVar("sort") ?? "";
        $filters = $this->request->getVar("filter") ?? [];
        $endDate = $this->request->getVar("end_date"); // Ambil end_date dari request
        $enablePagination =
            filter_var(
                $this->request->getVar("pagination"),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            ) ?? true;
        $offset = ($page - 1) * $limit;

        // Count query for total data
        $countQuery = "SELECT COUNT(DISTINCT l.loan_member_id) as total 
                   FROM loan l 
                   JOIN loan_detail ld ON l.loan_transaction_code = ld.loan_detail_loan_transaction_code";

        // Tambahkan kondisi untuk end_date jika ada
        if ($endDate) {
            $countQuery .= " WHERE l.loan_date <= ?";
            $totalData = $this->db->query($countQuery, [$endDate])->getRow()->total;
        } else {
            $totalData = $this->db->query($countQuery)->getRow()->total;
        }

        // Main query with aggregation to handle GROUP BY issues
        $baseQuery = "
    SELECT 
        l.loan_member_id as member_id,
        MAX(l.loan_member_institution) as institution,  -- Using MAX() for non-grouped columns
        MAX(l.loan_member_full_name) as full_name,
        MAX(l.loan_date) as loan_date,  -- Using MAX() to get the latest loan date
        COUNT(ld.loan_detail_book_id) as total_books,
        SUM(CASE WHEN ld.loan_detail_status IN ('Broken', 'Missing') THEN 1 ELSE 0 END) as damaged_books
    FROM loan l
    JOIN loan_detail ld ON l.loan_transaction_code = ld.loan_detail_loan_transaction_code
    ";

        // Filtering
        $conditions = [];
        $params = [];

        if ($search) {
            $conditions[] =
                "(l.loan_member_institution LIKE ? OR l.loan_member_full_name LIKE ?)";
            $searchTerm = "%" . $search . "%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // Filter mapping
        $filterMapping = [
            "member_id" => "l.loan_member_id",
            "institution" => "l.loan_member_institution",
            "full_name" => "l.loan_member_full_name",
        ];

        foreach ($filters as $key => $value) {
            if (!empty($value) && array_key_exists($key, $filterMapping)) {
                $conditions[] = "{$filterMapping[$key]} = ?";
                $params[] = $value;
            }
        }

        // Tambahkan kondisi untuk end_date jika ada
        if ($endDate) {
            $conditions[] = "l.loan_date <= ?";
            $params[] = $endDate;
        }

        if (count($conditions) > 0) {
            $baseQuery .= " WHERE " . implode(" AND ", $conditions);
        }

        // Grouping and ordering
        $baseQuery .= " GROUP BY l.loan_member_id";

        // Sorting
        if (!empty($sort)) {
            $sortDirection = $sort[0] === "-" ? "DESC" : "ASC";
            $sortField = ltrim($sort, "-");
            // Pastikan sortField adalah kolom yang valid
            if (
                in_array($sortField, [
                    "loan_member_id",
                    "loan_member_institution",
                    "loan_member_full_name",
                    "loan_date",
                    "total_books",
                    "damaged_books",
                ])
            ) {
                $baseQuery .= " ORDER BY $sortField $sortDirection";
            } else {
                $baseQuery .= " ORDER BY MAX(l.loan_date) DESC"; // Default sorting
            }
        } else {
            $baseQuery .= " ORDER BY MAX(l.loan_date) DESC"; // Default sorting
        }

        // Pagination
        if ($enablePagination) {
            $baseQuery .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
        }

        // Execute query
        try {
            $activities = $this->db->query($baseQuery, $params)->getResult();

            // Calculate pagination
            $pagination = new \stdClass();
            if ($enablePagination) {
                $totalPages = ceil($totalData / $limit);
                $pagination = [
                    "total_data" => (int) $totalData,
                    "jumlah_page" => (int) $totalPages,
                    "prev" => $page > 1 ? $page - 1 : null,
                    "page" => (int) $page,
                    "next" => $page < $totalPages ? $page + 1 : null,
                    "start" => ($page - 1) * $limit + 1,
                    "end" => min($page * $limit, $totalData),
                    "detail" => range(
                        max(1, $page - 2),
                        min($totalPages, $page + 2)
                    ),
                ];
            }

            return $this->response->setJSON([
                "status" => 200,
                "message" =>
                    "Berhasil mendapatkan data aktivitas detail anggota.",
                "result" => [
                    "data" => $activities,
                    "pagination" => $pagination,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                "status" => 500,
                "message" => "Gagal mendapatkan data aktivitas detail anggota.",
                "error" => $e->getMessage(),
            ]);
        }
    }

    public function count_books_status()
    {

        // Ambil parameter tanggal dari request
        $startDate = $this->request->getVar("start_date"); // Tanggal awal
        $endDate = $this->request->getVar("end_date"); // Tanggal akhir

        // Books Status
        $booksStatusQuery = "
    SELECT 
        SUM(CASE WHEN loan_detail_status = 'Broken' THEN 1 ELSE 0 END) as damaged_count,
        SUM(CASE WHEN loan_detail_status = 'Missing' THEN 1 ELSE 0 END) as missing_count,
        SUM(CASE WHEN loan_detail_status = 'Borrowed' THEN 1 ELSE 0 END) as borrowed_count,
        (SELECT COUNT(*) FROM books) as total_books
    FROM loan_detail";

        // Jika ada filter tanggal, tambahkan ke query
        if ($startDate && $endDate) {
            $booksStatusQuery .=
                " WHERE loan_detail_borrow_date BETWEEN ? AND ?";
            $booksStatus = $this->db
                ->query($booksStatusQuery, [$startDate, $endDate])
                ->getRow();
        } else {
            $booksStatus = $this->db->query($booksStatusQuery)->getRow();
        }

        // Loans Activity
        $loansActivityQuery = "
    SELECT 
        SUM(CASE WHEN DATE(loan_date) = CURDATE() THEN 1 ELSE 0 END) as today_loans,
        SUM(CASE WHEN YEARWEEK(loan_date, 1) = YEARWEEK(CURDATE(), 1) THEN 1 ELSE 0 END) as this_week_loans,
        SUM(CASE WHEN loan_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as last_30_days_loans,
        COUNT(*) as total_loans
    FROM loan";

        // Tambahkan filter berdasarkan tanggal jika ada
        if ($startDate && $endDate) {
            $loansActivityQuery .= " WHERE loan_date BETWEEN ? AND ?";
            $loansActivity = $this->db
                ->query($loansActivityQuery, [$startDate, $endDate])
                ->getRow();
        } else {
            $loansActivity = $this->db->query($loansActivityQuery)->getRow();
        }

        // Users Statistics (We are using loan_member_id from the loan table)
        $usersStatsQuery = "
    SELECT 
        (SELECT COUNT(DISTINCT loan_member_id) FROM loan) as total_members
    FROM dual";
        $usersStats = $this->db->query($usersStatsQuery)->getRow();

        // Library Resources (Publishers and Authors based on book data from loan_detail)
        $libraryResourcesQuery = "
    SELECT 
        (SELECT COUNT(DISTINCT loan_detail_book_publisher_name) FROM loan_detail) as total_publishers,
        (SELECT COUNT(DISTINCT loan_detail_book_author_name) FROM loan_detail) as total_authors
    FROM dual";
        $libraryResources = $this->db->query($libraryResourcesQuery)->getRow();

        // Calculate percentages and additional metrics
        $availableBooks =
            $booksStatus->total_books -
            $booksStatus->borrowed_count -
            $booksStatus->damaged_count -
            $booksStatus->missing_count;
        $borrowedPercentage =
            $booksStatus->total_books > 0
            ? ($booksStatus->borrowed_count / $booksStatus->total_books) *
            100
            : 0;
        $averageDailyLoans =
            $loansActivity->last_30_days_loans > 0
            ? $loansActivity->last_30_days_loans / 30
            : 0;

        // Prepare response data
        $result = [
            "data" => [
                "books_status" => [
                    "total" => (int) $booksStatus->total_books,
                    "available" => (int) $availableBooks,
                    "borrowed" => (int) $booksStatus->borrowed_count,
                    "damaged" => (int) $booksStatus->damaged_count,
                    "missing" => (int) $booksStatus->missing_count,
                    "borrowed_percentage" => round($borrowedPercentage, 2),
                ],
                "loans_activity" => [
                    "today" => (int) $loansActivity->today_loans,
                    "this_week" => (int) $loansActivity->this_week_loans,
                    "last_30_days" => (int) $loansActivity->last_30_days_loans,
                    "total" => (int) $loansActivity->total_loans,
                    "average_daily" => round($averageDailyLoans, 2),
                ],
                "users_statistics" => [
                    "total_members" => (int) $usersStats->total_members,
                ],
                "library_resources" => [
                    "total_publishers" =>
                        (int) $libraryResources->total_publishers,
                    "total_authors" => (int) $libraryResources->total_authors,
                ],
            ],
        ];

        return $this->respondWithSuccess(
            "Berhasil mengembalikan data statistik",
            $result
        );
    }

    public function getStockHistoryByBookId()
    {

        $bookId = $this->request->getVar("id") ?? 10;

        // Validate book ID
        if (!$bookId || !is_numeric($bookId)) {
            return $this->respondWithError(
                "Invalid book ID provided",
                null,
                400
            );
        }

        try {
            // Check if book exists
            $bookQuery = "SELECT * FROM books WHERE book_id = ?";
            $book = $this->db->query($bookQuery, [$bookId])->getRow();

            if (!$book) {
                return $this->respondWithError("Book not found", null, 404);
            }

            // Get stock history with book details
            $query = "SELECT 
                    sh.*,
                    b.books_title,
                    b.books_isbn,
                    b.books_publication_year,
                    b.books_stock_quantity as current_stock
                 FROM stock_history sh
                 JOIN books b ON sh.book_id = b.book_id
                 WHERE sh.book_id = ?
                 ORDER BY sh.created_at DESC";

            $history = $this->db->query($query, [$bookId])->getResult();

            if (empty($history)) {
                return $this->respond([
                    "status" => 200,
                    "message" => "No stock history found for this book",
                    "result" => [
                        "book" => [
                            "book_id" => $book->book_id,
                            "title" => $book->books_title,
                            "current_stock" => $book->books_stock_quantity,
                            "isbn" => $book->books_isbn,
                        ],
                        "history" => [],
                    ],
                ]);
            }

            return $this->respond([
                "status" => 200,
                "message" => "Stock history retrieved successfully",
                "result" => [
                    "book" => [
                        "book_id" => $book->book_id,
                        "title" => $book->books_title,
                        "current_stock" => $book->books_stock_quantity,
                        "isbn" => $book->books_isbn,
                    ],
                    "history" => $history,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->respondWithError(
                "Failed to retrieve stock history: " . $e->getMessage()
            );
        }
    }

    public function getAllStockHistory()
    {

        try {
            // Validasi token (opsional, sesuaikan dengan kebutuhan)
            $tokenValidation = $this->validateToken("warehouse,superadmin");
            if ($tokenValidation !== true) {
                return $this->respond(
                    $tokenValidation,
                    $tokenValidation["status"]
                );
            }

            // Pagination parameters
            $page = (int) ($this->request->getVar("page") ?? 1);
            $limit = (int) ($this->request->getVar("limit") ?? 10);
            $offset = ($page - 1) * $limit;

            // Optional filters
            $bookId = $this->request->getVar("book_id");
            $type = $this->request->getVar("type");
            $startDate = $this->request->getVar("start_date");
            $endDate = $this->request->getVar("end_date");
            $search = $this->request->getVar("search");

            // Base query
            $baseQuery = "FROM stock_history sh
                     JOIN books b ON sh.book_id = b.book_id
                     WHERE 1=1";
            $params = [];

            // Apply filters
            $conditions = [];
            if ($bookId) {
                $conditions[] = "sh.book_id = ?";
                $params[] = $bookId;
            }
            if ($type) {
                $conditions[] = "sh.type = ?";
                $params[] = $type;
            }
            if ($startDate) {
                $conditions[] = "DATE(sh.created_at) >= ?";
                $params[] = $startDate;
            }
            if ($endDate) {
                $conditions[] = "DATE(sh.created_at) <= ?";
                $params[] = $endDate;
            }

            // Global search
            if ($search) {
                $searchConditions = [
                    "b.books_title LIKE ?",
                    "b.books_isbn LIKE ?",
                    "sh.type LIKE ?",
                ];
                $conditions[] = "(" . implode(" OR ", $searchConditions) . ")";
                $searchParam = "%{$search}%";
                $params = array_merge(
                    $params,
                    array_fill(0, count($searchConditions), $searchParam)
                );
            }

            // Add conditions to base query
            if (!empty($conditions)) {
                $baseQuery .= " AND " . implode(" AND ", $conditions);
            }

            // Count total records for pagination
            $countQuery = "SELECT COUNT(*) as total " . $baseQuery;
            $totalRecords = $this->db->query($countQuery, $params)->getRow()->total;

            // Prepare pagination details
            $totalPages = ceil($totalRecords / $limit);
            $prevPage = $page > 1 ? $page - 1 : null;
            $nextPage = $page < $totalPages ? $page + 1 : null;

            // Get paginated data
            $query =
                "SELECT 
                    sh.*,
                    b.books_title,
                    b.books_isbn,
                    b.books_publication_year,
                    b.books_stock_quantity as current_stock
                 " .
                $baseQuery .
                "
                 ORDER BY sh.created_at DESC
                 LIMIT ? OFFSET ?";

            // Add pagination parameters
            $params[] = $limit;
            $params[] = $offset;

            $history = $this->db->query($query, $params)->getResult();

            // Format response data
            $formattedHistory = array_map(function ($item) {
                return [
                    "id" => $item->book_id,
                    "title" => $item->books_title,
                    "isbn" => $item->books_isbn,
                    "stock_history" => [
                        "type" => $item->type,
                        "quantity_change" => $item->quantity_change,
                        "stock_before" => $item->stock_before,
                        "stock_after" => $item->stock_after,
                        "timestamp" => $item->created_at,
                    ],
                ];
            }, $history);

            // Pagination detail calculation
            $paginationDetail = range(
                max(1, $page - 2),
                min($totalPages, $page + 2)
            );

            // Prepare pagination response
            $paginationResponse = [
                "total_data" => $totalRecords,
                "total_pages" => $totalPages,
                "prev" => $prevPage,
                "page" => $page,
                "next" => $nextPage,
                "detail" => $paginationDetail,
                "start" => $offset + 1,
                "end" => min($offset + $limit, $totalRecords),
            ];

            // Return response
            return $this->respond([
                "status" => 200,
                "message" => "Stock history retrieved successfully",
                "error" => "",
                "result" => [
                    "data" => $formattedHistory,
                    "pagination" => $paginationResponse,
                ],
            ]);
        } catch (\Exception $e) {
            // Error handling
            return $this->respond(
                [
                    "status" => 500,
                    "message" => "Failed to retrieve stock history",
                    "error" => $e->getMessage(),
                ],
                500
            );
        }
    }

    // EXPORT TO EXCEL

    public function export_most_borrowed_books()
    {

        // Query tanpa pagination untuk mendapatkan semua data
        $baseQuery = "
    SELECT 
        loan_detail_book_id AS id,
        loan_detail_book_title AS title,
        loan_detail_book_publisher_name AS publisher_name,
        loan_detail_book_author_name AS author_name,
        loan_detail_book_isbn AS isbn,
        loan_detail_book_publication_year AS publication_year,
        COUNT(*) AS borrow_count
    FROM loan_detail
    GROUP BY 
        loan_detail_book_id, 
        loan_detail_book_title, 
        loan_detail_book_publisher_name, 
        loan_detail_book_author_name,
        loan_detail_book_isbn,
        loan_detail_book_publication_year
    ORDER BY borrow_count DESC
    ";

        $books = $this->db->query($baseQuery)->getResult();

        // Membuat spreadsheet baru
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set judul
        $sheet->setTitle("Buku Paling Sering Dipinjam");

        // Header
        $headers = [
            "No",
            "Judul Buku",
            "Penerbit",
            "Penulis",
            "ISBN",
            "Tahun Terbit",
            "Jumlah Dipinjam",
        ];

        // Menulis header
        $col = "A";
        foreach ($headers as $header) {
            $sheet->setCellValue($col . "1", $header);
            $col++;
        }

        // Styling header
        $headerStyle = [
            "font" => ["bold" => true],
            "alignment" => [
                "horizontal" =>
                    \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ],
            "fill" => [
                "fillType" => Fill::FILL_SOLID,
                "startColor" => ["argb" => "FFE5E5E5"],
            ],
            "borders" => [
                "allBorders" => [
                    "borderStyle" => Border::BORDER_THIN,
                ],
            ],
        ];
        $sheet->getStyle("A1:G1")->applyFromArray($headerStyle);

        // Menulis data
        $row = 2;
        foreach ($books as $index => $book) {
            $sheet->setCellValue("A" . $row, $index + 1);
            $sheet->setCellValue("B" . $row, $book->title);
            $sheet->setCellValue("C" . $row, $book->publisher_name);
            $sheet->setCellValue("D" . $row, $book->author_name);
            $sheet->setCellValue("E" . $row, $book->isbn);
            $sheet->setCellValue("F" . $row, $book->publication_year);
            $sheet->setCellValue("G" . $row, $book->borrow_count);
            $row++;
        }

        // Auto width kolom
        foreach (range("A", "G") as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        // Styling data
        $dataStyle = [
            "borders" => [
                "allBorders" => [
                    "borderStyle" => Border::BORDER_THIN,
                ],
            ],
        ];
        $sheet->getStyle("A2:G" . ($row - 1))->applyFromArray($dataStyle);

        // Menyiapkan file untuk download
        $filename = "Buku_Paling_Sering_Dipinjam_" . date("YmdHis") . ".xlsx";

        // Set headers untuk download
        header(
            "Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
        );
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header("Cache-Control: max-age=0");

        // Membuat file Excel
        $writer = new Xlsx($spreadsheet);
        $writer->save("php://output");
        exit();
    }

    public function export_least_borrowed_books()
    {

        // Query tanpa pagination untuk mendapatkan semua data
        $baseQuery = "SELECT 
        loan_detail_book_id as id,
        MAX(loan_detail_book_title) as title,
        MAX(loan_detail_book_publisher_name) as publisher_name,
        MAX(loan_detail_book_author_name) as author_name,
        MAX(loan_detail_book_isbn) as isbn,
        MAX(loan_detail_book_publication_year) as publication_year,
        COUNT(*) as borrow_count
    FROM loan_detail
    GROUP BY loan_detail_book_id
    ORDER BY borrow_count ASC";

        $books = $this->db->query($baseQuery)->getResult();

        // Membuat spreadsheet baru
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set judul
        $sheet->setTitle("Buku Paling Jarang Dipinjam");

        // Header
        $headers = [
            "No",
            "Judul Buku",
            "Penerbit",
            "Penulis",
            "ISBN",
            "Tahun Terbit",
            "Jumlah Dipinjam",
        ];

        // Menulis header
        $col = "A";
        foreach ($headers as $header) {
            $sheet->setCellValue($col . "1", $header);
            $col++;
        }

        // Styling header
        $headerStyle = [
            "font" => ["bold" => true],
            "alignment" => [
                "horizontal" =>
                    \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ],
            "fill" => [
                "fillType" => Fill::FILL_SOLID,
                "startColor" => ["argb" => "FFE5E5E5"],
            ],
            "borders" => [
                "allBorders" => [
                    "borderStyle" => Border::BORDER_THIN,
                ],
            ],
        ];
        $sheet->getStyle("A1:G1")->applyFromArray($headerStyle);

        // Menulis data
        $row = 2;
        foreach ($books as $index => $book) {
            $sheet->setCellValue("A" . $row, $index + 1);
            $sheet->setCellValue("B" . $row, $book->title);
            $sheet->setCellValue("C" . $row, $book->publisher_name);
            $sheet->setCellValue("D" . $row, $book->author_name);
            $sheet->setCellValue("E" . $row, $book->isbn);
            $sheet->setCellValue("F" . $row, $book->publication_year);
            $sheet->setCellValue("G" . $row, $book->borrow_count);
            $row++;
        }

        // Auto width kolom
        foreach (range("A", "G") as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        // Styling data
        $dataStyle = [
            "borders" => [
                "allBorders" => [
                    "borderStyle" => Border::BORDER_THIN,
                ],
            ],
        ];
        $sheet->getStyle("A2:G" . ($row - 1))->applyFromArray($dataStyle);

        // Menyiapkan file untuk download
        $filename = "Buku_Paling_Jarang_Dipinjam_" . date("YmdHis") . ".xlsx";

        // Set headers untuk download
        header(
            "Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
        );
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header("Cache-Control: max-age=0");

        // Membuat file Excel
        $writer = new Xlsx($spreadsheet);
        $writer->save("php://output");
        exit();
    }

    public function export_inactive_users()
    {

        // Query tanpa pagination untuk mendapatkan semua data
        $baseQuery = "SELECT 
        loan_member_id as id,
        MAX(loan_member_institution) as institution,
        MAX(loan_member_email) as email,
        MAX(loan_member_full_name) as full_name,
        MAX(loan_member_address) as address,
        COUNT(*) as loan_count
    FROM loan
    GROUP BY loan_member_id
    ORDER BY loan_count DESC";

        $users = $this->db->query($baseQuery)->getResult();

        // Membuat spreadsheet baru
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set judul
        $sheet->setTitle("Pengguna Tidak Aktif");

        // Header
        $headers = [
            "No",
            "Institusi",
            "Email",
            "Nama Lengkap",
            "Alamat",
            "Jumlah Pinjaman",
        ];

        // Menulis header
        $col = "A";
        foreach ($headers as $header) {
            $sheet->setCellValue($col . "1", $header);
            $col++;
        }

        // Styling header
        $headerStyle = [
            "font" => ["bold" => true],
            "alignment" => [
                "horizontal" =>
                    \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ],
            "fill" => [
                "fillType" => Fill::FILL_SOLID,
                "startColor" => ["argb" => "FFE5E5E5"],
            ],
            "borders" => [
                "allBorders" => [
                    "borderStyle" => Border::BORDER_THIN,
                ],
            ],
        ];
        $sheet->getStyle("A1:F1")->applyFromArray($headerStyle);

        // Menulis data
        $row = 2;
        foreach ($users as $index => $user) {
            $sheet->setCellValue("A" . $row, $index + 1);
            $sheet->setCellValue("B" . $row, $user->institution);
            $sheet->setCellValue("C" . $row, $user->email);
            $sheet->setCellValue("D" . $row, $user->full_name);
            $sheet->setCellValue("E" . $row, $user->address);
            $sheet->setCellValue("F" . $row, $user->loan_count);
            $row++;
        }

        // Auto width kolom
        foreach (range("A", "F") as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        // Styling data
        $dataStyle = [
            "borders" => [
                "allBorders" => [
                    "borderStyle" => Border::BORDER_THIN,
                ],
            ],
        ];
        $sheet->getStyle("A2:F" . ($row - 1))->applyFromArray($dataStyle);

        // Menyiapkan file untuk download
        $filename = "Pengguna_Tidak_Aktif_" . date("YmdHis") . ".xlsx";

        // Set headers untuk download
        header(
            "Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
        );
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header("Cache-Control: max-age=0");

        // Membuat file Excel
        $writer = new Xlsx($spreadsheet);
        $writer->save("php://output");
        exit();
    }

    public function export_broken_missing_books()
    {

        // Query untuk mendapatkan semua data buku rusak dan hilang
        $baseQuery = "SELECT 
        loan_detail_book_id as id,
        loan_detail_book_title as title,
        loan_detail_book_publisher_name as publisher_name,
        loan_detail_status as status,
        loan_detail_borrow_date as borrow_date,
        loan_detail_return_date as return_date
    FROM loan_detail
    WHERE loan_detail_status IN ('Broken', 'Missing')
    ORDER BY loan_detail_borrow_date DESC";

        $books = $this->db->query($baseQuery)->getResult();

        // Membuat spreadsheet baru
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set judul
        $sheet->setTitle("Buku Rusak dan Hilang");

        // Header
        $headers = [
            "No",
            "Judul Buku",
            "Penerbit",
            "Status",
            "Tanggal Pinjam",
            "Tanggal Kembali",
        ];

        // Menulis header
        $col = "A";
        foreach ($headers as $header) {
            $sheet->setCellValue($col . "1", $header);
            $col++;
        }

        // Styling header
        $headerStyle = [
            "font" => ["bold" => true],
            "alignment" => ["horizontal" => Alignment::HORIZONTAL_CENTER],
            "fill" => [
                "fillType" => Fill::FILL_SOLID,
                "startColor" => ["argb" => "FFE5E5E5"],
            ],
            "borders" => [
                "allBorders" => [
                    "borderStyle" => Border::BORDER_THIN,
                ],
            ],
        ];
        $sheet->getStyle("A1:F1")->applyFromArray($headerStyle);

        // Menulis data
        $row = 2;
        foreach ($books as $index => $book) {
            $sheet->setCellValue("A" . $row, $index + 1);
            $sheet->setCellValue("B" . $row, $book->title);
            $sheet->setCellValue("C" . $row, $book->publisher_name);
            $sheet->setCellValue("D" . $row, $book->status);
            $sheet->setCellValue("E" . $row, $book->borrow_date);
            $sheet->setCellValue("F" . $row, $book->return_date);
            $row++;
        }

        // Auto width kolom
        foreach (range("A", "F") as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        // Styling data
        $dataStyle = [
            "borders" => [
                "allBorders" => [
                    "borderStyle" => Border::BORDER_THIN,
                ],
            ],
        ];
        $sheet->getStyle("A2:F" . ($row - 1))->applyFromArray($dataStyle);

        // Tambahkan ringkasan
        $summaryQuery = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN loan_detail_status = 'Broken' THEN 1 ELSE 0 END) as total_broken,
        SUM(CASE WHEN loan_detail_status = 'Missing' THEN 1 ELSE 0 END) as total_missing
    FROM loan_detail 
    WHERE loan_detail_status IN ('Broken', 'Missing')";

        $summary = $this->db->query($summaryQuery)->getRow();

        // Tambahkan sheet ringkasan
        $summarySheet = $spreadsheet->createSheet();
        $summarySheet->setTitle("Ringkasan");

        $summarySheet->setCellValue("A1", "Total Buku Rusak dan Hilang");
        $summarySheet->setCellValue("B1", $summary->total);
        $summarySheet->setCellValue("A2", "Total Buku Rusak");
        $summarySheet->setCellValue("B2", $summary->total_broken);
        $summarySheet->setCellValue("A3", "Total Buku Hilang");
        $summarySheet->setCellValue("B3", $summary->total_missing);

        // Styling ringkasan
        $summarySheet->getStyle("A1:B3")->applyFromArray([
            "borders" => [
                "allBorders" => [
                    "borderStyle" => Border::BORDER_THIN,
                ],
            ],
        ]);

        // Menyiapkan file untuk download
        $filename = "Buku_Rusak_Hilang_" . date("YmdHis") . ".xlsx";

        // Set headers untuk download
        header(
            "Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
        );
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header("Cache-Control: max-age=0");

        // Membuat file Excel
        $writer = new Xlsx($spreadsheet);
        $writer->save("php://output");
        exit();
    }

    public function export_detailed_member_activity()
    {

        $search = $this->request->getVar("search");
        $sort = $this->request->getVar("sort") ?? "";
        $filters = $this->request->getVar("filter") ?? [];
        $endDate = $this->request->getVar("end_date"); // Ambil end_date dari request

        // Main query with aggregation to handle GROUP BY issues
        $baseQuery = "
    SELECT 
        l.loan_member_id as member_id,
        MAX(l.loan_member_institution) as institution,
        MAX(l.loan_member_full_name) as full_name,
        MAX(l.loan_date) as loan_date,
        COUNT(ld.loan_detail_book_id) as total_books,
        SUM(CASE WHEN ld.loan_detail_status IN ('Broken', 'Missing') THEN 1 ELSE 0 END) as damaged_books
    FROM loan l
    JOIN loan_detail ld ON l.loan_transaction_code = ld.loan_detail_loan_transaction_code
    ";

        // Filtering
        $conditions = [];
        $params = [];

        if ($search) {
            $conditions[] =
                "(l.loan_member_institution LIKE ? OR l.loan_member_full_name LIKE ?)";
            $searchTerm = "%" . $search . "%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // Filter mapping
        $filterMapping = [
            "member_id" => "l.loan_member_id",
            "institution" => "l.loan_member_institution",
            "full_name" => "l.loan_member_full_name",
        ];

        foreach ($filters as $key => $value) {
            if (!empty($value) && array_key_exists($key, $filterMapping)) {
                $conditions[] = "{$filterMapping[$key]} = ?";
                $params[] = $value;
            }
        }

        // Tambahkan kondisi untuk end_date jika ada
        if ($endDate) {
            $conditions[] = "l.loan_date <= ?";
            $params[] = $endDate;
        }

        if (count($conditions) > 0) {
            $baseQuery .= " WHERE " . implode(" AND ", $conditions);
        }

        // Grouping
        $baseQuery .= " GROUP BY l.loan_member_id";

        // Sorting
        if (!empty($sort)) {
            $sortDirection = $sort[0] === "-" ? "DESC" : "ASC";
            $sortField = ltrim($sort, "-");
            if (
                in_array($sortField, [
                    "loan_member_id",
                    "loan_member_institution",
                    "loan_member_full_name",
                    "loan_date",
                    "total_books",
                    "damaged_books",
                ])
            ) {
                $baseQuery .= " ORDER BY $sortField $sortDirection";
            } else {
                $baseQuery .= " ORDER BY MAX(l.loan_date) DESC"; // Default sorting
            }
        } else {
            $baseQuery .= " ORDER BY MAX(l.loan_date) DESC"; // Default sorting
        }

        // Execute query
        try {
            $activities = $this->db->query($baseQuery, $params)->getResult();

            // Membuat spreadsheet baru
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set judul
            $sheet->setTitle("Aktivitas Anggota");

            // Header
            $headers = [
                "No",
                "ID Anggota",
                "Institusi",
                "Nama Lengkap",
                "Tanggal Pinjam",
                "Total Buku",
                "Buku Rusak/Hilang",
            ];

            // Menulis header
            $col = "A";
            foreach ($headers as $header) {
                $sheet->setCellValue($col . "1", $header);
                $col++;
            }

            // Styling header
            $headerStyle = [
                "font" => ["bold" => true],
                "alignment" => [
                    "horizontal" =>
                        \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                ],
                "fill" => [
                    "fillType" =>
                        \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    "startColor" => ["argb" => "FFE5E5E5"],
                ],
                "borders" => [
                    "allBorders" => [
                        "borderStyle" =>
                            \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    ],
                ],
            ];
            $sheet->getStyle("A1:G1")->applyFromArray($headerStyle);

            // Menulis data
            $row = 2;
            foreach ($activities as $index => $item) {
                $sheet->setCellValue("A" . $row, $index + 1);
                $sheet->setCellValue("B" . $row, $item->member_id);
                $sheet->setCellValue("C" . $row, $item->institution);
                $sheet->setCellValue("D" . $row, $item->full_name);
                $sheet->setCellValue("E" . $row, $item->loan_date);
                $sheet->setCellValue("F" . $row, $item->total_books);
                $sheet->setCellValue("G" . $row, $item->damaged_books);
                $row++;
            }

            // Auto width kolom
            foreach (range("A", "G") as $columnID) {
                $sheet->getColumnDimension($columnID)->setAutoSize(true);
            }

            // Styling data
            $dataStyle = [
                "borders" => [
                    "allBorders" => [
                        "borderStyle" =>
                            \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    ],
                ],
            ];
            $sheet->getStyle("A2:G" . ($row - 1))->applyFromArray($dataStyle);

            // Menyiapkan file untuk download
            $filename = "Aktivitas_Anggota_" . date("YmdHis") . ".xlsx";

            // Set headers untuk download
            header(
                "Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
            );
            header(
                'Content-Disposition: attachment;filename="' . $filename . '"'
            );
            header("Cache-Control: max-age=0");

            // Membuat file Excel
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save("php://output");
            exit();
        } catch (\Exception $e) {
            return $this->respond(
                [
                    "status" => 500,
                    "message" =>
                        "Gagal mengekspor aktivitas anggota: " .
                        $e->getMessage(),
                ],
                500
            );
        }
    }


    public function export_all_stock_history()
    {

        try {
            // Validasi token (opsional, sesuaikan dengan kebutuhan)
            $tokenValidation = $this->validateToken("warehouse,superadmin");
            if ($tokenValidation !== true) {
                return $this->respond(
                    $tokenValidation,
                    $tokenValidation["status"]
                );
            }

            // Optional filters
            $bookId = $this->request->getVar("book_id");
            $type = $this->request->getVar("type");
            $startDate = $this->request->getVar("start_date");
            $endDate = $this->request->getVar("end_date");
            $search = $this->request->getVar("search");

            // Base query
            $baseQuery = "FROM stock_history sh
                     JOIN books b ON sh.book_id = b.book_id
                     WHERE 1=1";
            $params = [];

            // Apply filters
            $conditions = [];
            if ($bookId) {
                $conditions[] = "sh.book_id = ?";
                $params[] = $bookId;
            }
            if ($type) {
                $conditions[] = "sh.type = ?";
                $params[] = $type;
            }
            if ($startDate) {
                $conditions[] = "DATE(sh.created_at) >= ?";
                $params[] = $startDate;
            }
            if ($endDate) {
                $conditions[] = "DATE(sh.created_at) <= ?";
                $params[] = $endDate;
            }

            // Global search
            if ($search) {
                $searchConditions = [
                    "b.books_title LIKE ?",
                    "b.books_isbn LIKE ?",
                    "sh.type LIKE ?",
                ];
                $conditions[] = "(" . implode(" OR ", $searchConditions) . ")";
                $searchParam = "%{$search}%";
                $params = array_merge(
                    $params,
                    array_fill(0, count($searchConditions), $searchParam)
                );
            }

            // Add conditions to base query
            if (!empty($conditions)) {
                $baseQuery .= " AND " . implode(" AND ", $conditions);
            }

            // Get all data without pagination
            $query =
                "
            SELECT 
                sh.*,
                b.books_title,
                b.books_isbn,
                b.books_publication_year,
                b.books_stock_quantity as current_stock
            " .
                $baseQuery .
                "
            ORDER BY sh.created_at DESC";

            $history = $this->db->query($query, $params)->getResult();

            // Membuat spreadsheet baru
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set judul
            $sheet->setTitle("Riwayat Stok Buku");

            // Header
            $headers = [
                "No",
                "Judul Buku",
                "ISBN",
                "Tahun Terbit",
                "Stok Saat Ini",
                "Tipe Perubahan",
                "Perubahan Jumlah",
                "Stok Sebelum",
                "Stok Setelah",
                "Tanggal",
            ];

            // Menulis header
            $col = "A";
            foreach ($headers as $header) {
                $sheet->setCellValue($col . "1", $header);
                $col++;
            }

            // Styling header
            $headerStyle = [
                "font" => ["bold" => true],
                "alignment" => [
                    "horizontal" =>
                        \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                ],
                "fill" => [
                    "fillType" =>
                        \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    "startColor" => ["argb" => "FFE5E5E5"],
                ],
                "borders" => [
                    "allBorders" => [
                        "borderStyle" =>
                            \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    ],
                ],
            ];
            $sheet->getStyle("A1:J1")->applyFromArray($headerStyle);

            // Menulis data
            $row = 2;
            foreach ($history as $index => $item) {
                $sheet->setCellValue("A" . $row, $index + 1);
                $sheet->setCellValue("B" . $row, $item->books_title);
                $sheet->setCellValue("C" . $row, $item->books_isbn);
                $sheet->setCellValue("D" . $row, $item->books_publication_year);
                $sheet->setCellValue("E" . $row, $item->current_stock);
                $sheet->setCellValue("F" . $row, $item->type);
                $sheet->setCellValue("G" . $row, $item->quantity_change);
                $sheet->setCellValue("H" . $row, $item->stock_before);
                $sheet->setCellValue("I" . $row, $item->stock_after);
                $sheet->setCellValue("J" . $row, $item->created_at);
                $row++;
            }

            // Auto width kolom
            foreach (range("A", "J") as $columnID) {
                $sheet->getColumnDimension($columnID)->setAutoSize(true);
            }

            // Styling data
            $dataStyle = [
                "borders" => [
                    "allBorders" => [
                        "borderStyle" =>
                            \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    ],
                ],
            ];
            $sheet->getStyle("A2:J" . ($row - 1))->applyFromArray($dataStyle);

            // Menyiapkan file untuk download
            $filename = "Riwayat_Stok_Buku_" . date("YmdHis") . ".xlsx";

            // Set headers untuk download
            header(
                "Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
            );
            header(
                'Content-Disposition: attachment;filename="' . $filename . '"'
            );
            header("Cache-Control: max-age=0");

            // Membuat file Excel
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save("php://output");
            exit();
        } catch (\Exception $e) {
            return $this->respondWithError(
                "Failed to export stock history: " . $e->getMessage()
            );
        }
    }
}
