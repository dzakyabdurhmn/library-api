<?php
namespace App\Controllers;

use Config\Database;

class ReportController extends AuthorizationController
{
    public function most_borrowed_books()
    {
        $db = Database::connect();

        // Parameters from query string
        $limit = (int) ($this->request->getVar('limit') ?? 10);
        $page = (int) ($this->request->getVar('page') ?? 1);
        $search = $this->request->getVar('search');
        $paginate = $this->request->getVar('pagination') !== 'false';
        $offset = ($page - 1) * $limit;

        // Base query
        $query = "
        SELECT 
        loan_detail_book_id AS book_id, 
        COUNT(*) AS borrow_count,
        (SELECT books_title FROM books WHERE book_id = loan_detail_book_id) AS book_title,
        (SELECT books_publication_year FROM books WHERE book_id = loan_detail_book_id) AS publication_year,
        (SELECT books_isbn FROM books WHERE book_id = loan_detail_book_id) AS isbn,
        (SELECT books_price FROM books WHERE book_id = loan_detail_book_id) AS price,
        (SELECT books_stock_quantity FROM books WHERE book_id = loan_detail_book_id) AS stock_quantity,
        (SELECT books_barcode FROM books WHERE book_id = loan_detail_book_id) AS barcode,
        (SELECT books_publisher_id FROM books WHERE book_id = loan_detail_book_id) AS publisher_id,
        (SELECT publisher_name FROM publisher WHERE publisher_id = (SELECT books_publisher_id FROM books WHERE book_id = loan_detail_book_id)) AS publisher_name,
        (SELECT publisher_address FROM publisher WHERE publisher_id = (SELECT books_publisher_id FROM books WHERE book_id = loan_detail_book_id)) AS publisher_address,
        (SELECT publisher_phone FROM publisher WHERE publisher_id = (SELECT books_publisher_id FROM books WHERE book_id = loan_detail_book_id)) AS publisher_phone,
        (SELECT publisher_email FROM publisher WHERE publisher_id = (SELECT books_publisher_id FROM books WHERE book_id = loan_detail_book_id)) AS publisher_email,
        (SELECT books_author_id FROM books WHERE book_id = loan_detail_book_id) AS author_id,
        (SELECT author_name FROM author WHERE author_id = (SELECT books_author_id FROM books WHERE book_id = loan_detail_book_id)) AS author_name,
        (SELECT author_biography FROM author WHERE author_id = (SELECT books_author_id FROM books WHERE book_id = loan_detail_book_id)) AS author_biography
        FROM loan_detail
        WHERE loan_detail_status = 'Borrowed'";

        $conditions = [];
        $params = [];

        // Search conditions
        if ($search) {
            $conditions[] = "(EXISTS (SELECT 1 FROM books WHERE book_id = loan_detail_book_id AND (books_title LIKE ? OR books_isbn LIKE ?)))";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        // Additional filters
        $filters = [
            'book_id' => 'loan_detail_book_id',
            'publisher_id' => '(SELECT books_publisher_id FROM books WHERE book_id = loan_detail_book_id)',
            'publication_year' => '(SELECT books_publication_year FROM books WHERE book_id = loan_detail_book_id)',
            'author_id' => '(SELECT books_author_id FROM books WHERE book_id = loan_detail_book_id)',
            'price_min' => '(SELECT books_price FROM books WHERE book_id = loan_detail_book_id) >= ?',
            'price_max' => '(SELECT books_price FROM books WHERE book_id = loan_detail_book_id) <= ?',
            'book_title' => 'EXISTS (SELECT 1 FROM books WHERE book_id = loan_detail_book_id AND books_title LIKE ?)',
            'borrow_count' => 'COUNT(*) >= ?',
            'publisher_name' => 'EXISTS (SELECT 1 FROM publisher WHERE publisher_id = (SELECT books_publisher_id FROM books WHERE book_id = loan_detail_book_id) AND publisher_name LIKE ?)',
        ];

        foreach ($filters as $filterKey => $dbColumn) {
            $filterValue = $this->request->getVar("filter[$filterKey]");
            if ($filterValue !== null && $filterValue !== '') {
                if (in_array($filterKey, ['price_min', 'price_max', 'borrow_count'])) {
                    $conditions[] = $dbColumn;
                    $params[] = (float) $filterValue; // Ensure numeric value for price and borrow count
                } elseif (strpos($dbColumn, 'LIKE') !== false) {
                    $conditions[] = $dbColumn;
                    $params[] = "%$filterValue%";
                } else {
                    $conditions[] = "$dbColumn = ?";
                    $params[] = $filterValue;
                }
            }
        }

        // Add conditions to query
        if (!empty($conditions)) {
            $query .= ' AND ' . implode(' AND ', $conditions);
        }

        // Group and order by borrow count
        $query .= " GROUP BY loan_detail_book_id ORDER BY borrow_count DESC, book_title ASC";

        // Pagination
        if ($paginate) {
            $query .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
        }

        // Fetch borrowed books
        $borrowedBooks = $db->query($query, $params)->getResultArray();


        $borrowedBooks = array_map(function ($book) {
            // Simpan 'id' terlebih dahulu
            $id = $book['book_id'];
            unset($book['book_id']);  // Hapus 'book_id'

            // Letakkan 'id' di urutan teratas
            $book = array_merge(['id' => $id], $book);

            return $book;
        }, $borrowedBooks);

        // Pagination response
        $paginationResponse = [
            'total_data' => 0,
            'total_pages' => 0,
            'prev' => null,
            'page' => $page,
            'next' => null,
            'detail' => [],
            'start' => 0,
            'end' => 0,
        ];

        // Total count for pagination
        if ($paginate) {
            $totalQuery = "
            SELECT COUNT(DISTINCT loan_detail_book_id) AS total
            FROM loan_detail
            WHERE loan_detail_status = 'Borrowed'";

            if (!empty($conditions)) {
                $totalQuery .= ' AND ' . implode(' AND ', $conditions);
            }

            // Execute total query
            $total = $db->query($totalQuery, array_slice($params, 0, -2))->getRow()->total;

            // Update pagination response
            $paginationResponse = [
                'total_data' => (int) $total,
                'total_pages' => (int) ceil($total / $limit),
                'prev' => ($page > 1) ? $page - 1 : null,
                'page' => $page,
                'next' => ($page < ceil($total / $limit)) ? $page + 1 : null,
                'detail' => range(max(1, $page - 2), min(ceil($total / $limit), $page + 2)),
                'start' => ($page - 1) * $limit + 1,
                'end' => min($page * $limit, $total),
            ];
        }

        return $this->respondWithSuccess('Behasil mengembalikan data report.', [
            'data' => $borrowedBooks,
            'pagination' => $paginate ? $paginationResponse : (object) [],
        ]);
    }

    public function least_borrowed_books()
    {
        $db = Database::connect();

        // Parameters from query string
        $limit = (int) ($this->request->getVar('limit') ?? 10);
        $page = (int) ($this->request->getVar('page') ?? 1);
        $search = $this->request->getVar('search');
        $paginate = $this->request->getVar('pagination') !== 'false';
        $offset = ($page - 1) * $limit;

        // Base query
        $query = "
    SELECT 
    loan_detail_book_id AS book_id, 
    COUNT(*) AS borrow_count,
    (SELECT books_title FROM books WHERE book_id = loan_detail_book_id) AS book_title,
    (SELECT books_publication_year FROM books WHERE book_id = loan_detail_book_id) AS publication_year,
    (SELECT books_isbn FROM books WHERE book_id = loan_detail_book_id) AS isbn,
    (SELECT books_price FROM books WHERE book_id = loan_detail_book_id) AS price,
    (SELECT books_stock_quantity FROM books WHERE book_id = loan_detail_book_id) AS stock_quantity,
    (SELECT books_barcode FROM books WHERE book_id = loan_detail_book_id) AS barcode,
    (SELECT books_publisher_id FROM books WHERE book_id = loan_detail_book_id) AS publisher_id,
    (SELECT publisher_name FROM publisher WHERE publisher_id = (SELECT books_publisher_id FROM books WHERE book_id = loan_detail_book_id)) AS publisher_name,
    (SELECT publisher_address FROM publisher WHERE publisher_id = (SELECT books_publisher_id FROM books WHERE book_id = loan_detail_book_id)) AS publisher_address,
    (SELECT publisher_phone FROM publisher WHERE publisher_id = (SELECT books_publisher_id FROM books WHERE book_id = loan_detail_book_id)) AS publisher_phone,
    (SELECT publisher_email FROM publisher WHERE publisher_id = (SELECT books_publisher_id FROM books WHERE book_id = loan_detail_book_id)) AS publisher_email,
    (SELECT books_author_id FROM books WHERE book_id = loan_detail_book_id) AS author_id,
    (SELECT author_name FROM author WHERE author_id = (SELECT books_author_id FROM books WHERE book_id = loan_detail_book_id)) AS author_name,
    (SELECT author_biography FROM author WHERE author_id = (SELECT books_author_id FROM books WHERE book_id = loan_detail_book_id)) AS author_biography
    FROM loan_detail
    WHERE loan_detail_status = 'Borrowed'";

        $conditions = [];
        $params = [];

        // Expanded search conditions
        if ($search) {
            $conditions[] = "(
            EXISTS (
                SELECT 1 FROM books 
                WHERE book_id = loan_detail_book_id 
                AND (
                    books_title LIKE ? 
                    OR books_isbn LIKE ? 
                    OR books_publication_year LIKE ? 
                    OR books_barcode LIKE ?
                    OR EXISTS (
                        SELECT 1 FROM publisher 
                        WHERE publisher_id = books_publisher_id 
                        AND (
                            publisher_name LIKE ? 
                            OR publisher_address LIKE ? 
                            OR publisher_phone LIKE ? 
                            OR publisher_email LIKE ?
                        )
                    )
                    OR EXISTS (
                        SELECT 1 FROM author 
                        WHERE author_id = books_author_id 
                        AND (
                            author_name LIKE ? 
                            OR author_biography LIKE ?
                        )
                    )
                )
            )
        )";
            $searchParam = "%$search%";
            $params = array_merge($params, array_fill(0, 10, $searchParam));
        }

        // Additional filters (unchanged)
        $filters = [
            'id' => 'loan_detail_book_id',
            'publisher_id' => '(SELECT books_publisher_id FROM books WHERE book_id = loan_detail_book_id)',
            'publication_year' => '(SELECT books_publication_year FROM books WHERE book_id = loan_detail_book_id)',
            'author_id' => '(SELECT books_author_id FROM books WHERE book_id = loan_detail_book_id)',
            'price_min' => '(SELECT books_price FROM books WHERE book_id = loan_detail_book_id) >= ?',
            'price_max' => '(SELECT books_price FROM books WHERE book_id = loan_detail_book_id) <= ?',
            'book_title' => 'EXISTS (SELECT 1 FROM books WHERE book_id = loan_detail_book_id AND books_title LIKE ?)',
            'borrow_count' => 'COUNT(*) <= ?',
            'publisher_name' => 'EXISTS (SELECT 1 FROM publisher WHERE publisher_id = (SELECT books_publisher_id FROM books WHERE book_id = loan_detail_book_id) AND publisher_name LIKE ?)',
        ];

        foreach ($filters as $filterKey => $dbColumn) {
            $filterValue = $this->request->getVar("filter[$filterKey]");
            if ($filterValue !== null && $filterValue !== '') {
                if (in_array($filterKey, ['price_min', 'price_max', 'borrow_count'])) {
                    $conditions[] = $dbColumn;
                    $params[] = (float) $filterValue; // Ensure numeric value for price and borrow count
                } elseif (strpos($dbColumn, 'LIKE') !== false) {
                    $conditions[] = $dbColumn;
                    $params[] = "%$filterValue%";
                } else {
                    $conditions[] = "$dbColumn = ?";
                    $params[] = $filterValue;
                }
            }
        }

        // Add conditions to query
        if (!empty($conditions)) {
            $query .= ' AND ' . implode(' AND ', $conditions);
        }

        // Group and order by borrow count
        $query .= " GROUP BY loan_detail_book_id ORDER BY borrow_count ASC, book_title ASC";

        // Pagination
        if ($paginate) {
            $query .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
        }

        // Fetch borrowed books
        $borrowedBooks = $db->query($query, $params)->getResultArray();



        $borrowedBooks = array_map(function ($book) {
            // Simpan 'id' terlebih dahulu
            $id = $book['book_id'];
            unset($book['book_id']);  // Hapus 'book_id'

            // Letakkan 'id' di urutan teratas
            $book = array_merge(['id' => $id], $book);

            return $book;
        }, $borrowedBooks);

        // Pagination response
        $paginationResponse = [
            'total_data' => 0,
            'total_pages' => 0,
            'prev' => null,
            'page' => $page,
            'next' => null,
            'detail' => [],
            'start' => 0,
            'end' => 0,
        ];

        // Total count for pagination
        if ($paginate) {
            $totalQuery = "
        SELECT COUNT(DISTINCT loan_detail_book_id) AS total
        FROM loan_detail
        WHERE loan_detail_status = 'Borrowed'";

            if (!empty($conditions)) {
                $totalQuery .= ' AND ' . implode(' AND ', $conditions);
            }

            // Execute total query
            $total = $db->query($totalQuery, array_slice($params, 0, -2))->getRow()->total;

            // Update pagination response
            $paginationResponse = [
                'total_data' => (int) $total,
                'total_pages' => (int) ceil($total / $limit),
                'prev' => ($page > 1) ? $page - 1 : null,
                'page' => $page,
                'next' => ($page < ceil($total / $limit)) ? $page + 1 : null,
                'detail' => range(max(1, $page - 2), min(ceil($total / $limit), $page + 2)),
                'start' => ($page - 1) * $limit + 1,
                'end' => min($page * $limit, $total),
            ];
        }

        return $this->respondWithSuccess('Behasil mengembalikan data report', [
            'data' => $borrowedBooks,
            'pagination' => $paginate ? $paginationResponse : (object) [],
        ]);
    }


    public function broken_missing_books()
    {
        $db = Database::connect();

        // Parameters from query string
        $limit = (int) ($this->request->getVar('limit') ?? 10);
        $page = (int) ($this->request->getVar('page') ?? 1);
        $search = $this->request->getVar('search');
        $paginate = $this->request->getVar('pagination') !== 'false';
        $offset = ($page - 1) * $limit;

        // Base query
        $query = "
        SELECT 
        loan_detail_book_id AS book_id, 
        loan_detail_status AS status,
        COUNT(*) AS count,
        (SELECT books_title FROM books WHERE book_id = loan_detail_book_id) AS book_title,
        (SELECT books_publication_year FROM books WHERE book_id = loan_detail_book_id) AS publication_year,
        (SELECT books_isbn FROM books WHERE book_id = loan_detail_book_id) AS isbn,
        (SELECT books_price FROM books WHERE book_id = loan_detail_book_id) AS price,
        (SELECT books_stock_quantity FROM books WHERE book_id = loan_detail_book_id) AS stock_quantity,
        (SELECT books_barcode FROM books WHERE book_id = loan_detail_book_id) AS barcode,
        (SELECT books_publisher_id FROM books WHERE book_id = loan_detail_book_id) AS publisher_id,
        (SELECT publisher_name FROM publisher WHERE publisher_id = (SELECT books_publisher_id FROM books WHERE book_id = loan_detail_book_id)) AS publisher_name,
        (SELECT publisher_address FROM publisher WHERE publisher_id = (SELECT books_publisher_id FROM books WHERE book_id = loan_detail_book_id)) AS publisher_address,
        (SELECT publisher_phone FROM publisher WHERE publisher_id = (SELECT books_publisher_id FROM books WHERE book_id = loan_detail_book_id)) AS publisher_phone,
        (SELECT publisher_email FROM publisher WHERE publisher_id = (SELECT books_publisher_id FROM books WHERE book_id = loan_detail_book_id)) AS publisher_email,
        (SELECT books_author_id FROM books WHERE book_id = loan_detail_book_id) AS author_id,
        (SELECT author_name FROM author WHERE author_id = (SELECT books_author_id FROM books WHERE book_id = loan_detail_book_id)) AS author_name,
        (SELECT author_biography FROM author WHERE author_id = (SELECT books_author_id FROM books WHERE book_id = loan_detail_book_id)) AS author_biography
        FROM loan_detail
        WHERE loan_detail_status IN ('Broken', 'Missing')";

        $conditions = [];
        $params = [];

        // Expanded search conditions
        if ($search) {
            $conditions[] = "(
            EXISTS (
                SELECT 1 FROM books 
                WHERE book_id = loan_detail_book_id 
                AND (
                    books_title LIKE ? 
                    OR books_isbn LIKE ? 
                    OR books_publication_year LIKE ? 
                    OR books_barcode LIKE ?
                    OR EXISTS (
                        SELECT 1 FROM publisher 
                        WHERE publisher_id = books_publisher_id 
                        AND (
                            publisher_name LIKE ? 
                            OR publisher_address LIKE ? 
                            OR publisher_phone LIKE ? 
                            OR publisher_email LIKE ?
                        )
                    )
                    OR EXISTS (
                        SELECT 1 FROM author 
                        WHERE author_id = books_author_id 
                        AND (
                            author_name LIKE ? 
                            OR author_biography LIKE ?
                        )
                    )
                )
            )
        )";
            $searchParam = "%$search%";
            $params = array_merge($params, array_fill(0, 10, $searchParam));
        }

        // Additional filters
        $filters = [
            'book_id' => 'loan_detail_book_id',
            'publisher_id' => '(SELECT books_publisher_id FROM books WHERE book_id = loan_detail_book_id)',
            'publication_year' => '(SELECT books_publication_year FROM books WHERE book_id = loan_detail_book_id)',
            'author_id' => '(SELECT books_author_id FROM books WHERE book_id = loan_detail_book_id)',
            'price_min' => '(SELECT books_price FROM books WHERE book_id = loan_detail_book_id) >= ?',
            'price_max' => '(SELECT books_price FROM books WHERE book_id = loan_detail_book_id) <= ?',
            'book_title' => 'EXISTS (SELECT 1 FROM books WHERE book_id = loan_detail_book_id AND books_title LIKE ?)',
            'status' => 'loan_detail_status',
            'count' => 'COUNT(*) >= ?',
            'publisher_name' => 'EXISTS (SELECT 1 FROM publisher WHERE publisher_id = (SELECT books_publisher_id FROM books WHERE book_id = loan_detail_book_id) AND publisher_name LIKE ?)',
        ];

        foreach ($filters as $filterKey => $dbColumn) {
            $filterValue = $this->request->getVar("filter[$filterKey]");
            if ($filterValue !== null && $filterValue !== '') {
                if (in_array($filterKey, ['price_min', 'price_max', 'count'])) {
                    $conditions[] = $dbColumn;
                    $params[] = (float) $filterValue; // Ensure numeric value for price and count
                } elseif (strpos($dbColumn, 'LIKE') !== false) {
                    $conditions[] = $dbColumn;
                    $params[] = "%$filterValue%";
                } else {
                    $conditions[] = "$dbColumn = ?";
                    $params[] = $filterValue;
                }
            }
        }

        // Add conditions to query
        if (!empty($conditions)) {
            $query .= ' AND ' . implode(' AND ', $conditions);
        }

        // Group and order by count
        $query .= " GROUP BY loan_detail_book_id, loan_detail_status ORDER BY count DESC, book_title ASC";

        // Pagination
        if ($paginate) {
            $query .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
        }

        // Fetch broken and missing books
        $brokenMissingBooks = $db->query($query, $params)->getResultArray();

        // Pagination response
        $paginationResponse = [
            'total_data' => 0,
            'total_pages' => 0,
            'prev' => null,
            'page' => $page,
            'next' => null,
            'detail' => [],
            'start' => 0,
            'end' => 0,
        ];

        // Total count for pagination
        if ($paginate) {
            $totalQuery = "
        SELECT COUNT(*) AS total
        FROM (
            SELECT DISTINCT loan_detail_book_id, loan_detail_status
            FROM loan_detail
            WHERE loan_detail_status IN ('Broken', 'Missing')
        ) AS subquery";

            if (!empty($conditions)) {
                $totalQuery = "
            SELECT COUNT(*) AS total
            FROM (
                SELECT DISTINCT loan_detail_book_id, loan_detail_status
                FROM loan_detail
                WHERE loan_detail_status IN ('Broken', 'Missing')
                AND " . implode(' AND ', $conditions) . "
            ) AS subquery";
            }

            // Execute total query
            $total = $db->query($totalQuery, array_slice($params, 0, -2))->getRow()->total;

            // Update pagination response
            $paginationResponse = [
                'total_data' => (int) $total,
                'total_pages' => (int) ceil($total / $limit),
                'prev' => ($page > 1) ? $page - 1 : null,
                'page' => $page,
                'next' => ($page < ceil($total / $limit)) ? $page + 1 : null,
                'detail' => range(max(1, $page - 2), min(ceil($total / $limit), $page + 2)),
                'start' => ($page - 1) * $limit + 1,
                'end' => min($page * $limit, $total),
            ];
        }

        return $this->respondWithSuccess('Behasil mengembalikan data report.', [
            'data' => $brokenMissingBooks,
            'pagination' => $paginate ? $paginationResponse : (object) [],
        ]);
    }


    public function most_active_users()
    {
        $db = Database::connect();

        // Parameters from query string
        $limit = (int) ($this->request->getVar('limit') ?? 10);
        $page = (int) ($this->request->getVar('page') ?? 1);
        $search = $this->request->getVar('search');
        $paginate = $this->request->getVar('pagination') !== 'false';
        $offset = ($page - 1) * $limit;

        // Base query
        $query = "
    SELECT 
        m.member_id,
        m.member_username,
        m.member_email,
        m.member_full_name,
        m.member_address,
        m.member_job,
        m.member_status,
        m.member_religion,
        m.member_barcode,
        m.member_gender,
        (SELECT COUNT(*) FROM loan WHERE loan_member_id = m.member_id AND loan_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as activity_count
    FROM member m
    WHERE 1=1";

        $conditions = [];
        $params = [];

        // Search conditions
        if ($search) {
            $searchFields = ['m.member_username', 'm.member_email', 'm.member_full_name', 'm.member_address', 'm.member_job', 'm.member_religion', 'm.member_barcode'];
            $searchConditions = array_map(fn($field) => "$field LIKE ?", $searchFields);
            $conditions[] = '(' . implode(' OR ', $searchConditions) . ')';
            $params = array_merge($params, array_fill(0, count($searchFields), "%$search%"));
        }

        // Additional filters
        $filters = [
            'member_id' => 'm.member_id',
            'username' => 'm.member_username',
            'email' => 'm.member_email',
            'full_name' => 'm.member_full_name',
            'address' => 'm.member_address',
            'job' => 'm.member_job',
            'status' => 'm.member_status',
            'religion' => 'm.member_religion',
            'barcode' => 'm.member_barcode',
            'gender' => 'm.member_gender',
            'activity_count_min' => '(SELECT COUNT(*) FROM loan WHERE loan_member_id = m.member_id AND loan_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) >= ?',
            'activity_count_max' => '(SELECT COUNT(*) FROM loan WHERE loan_member_id = m.member_id AND loan_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) <= ?',
        ];

        foreach ($filters as $filterKey => $dbColumn) {
            $filterValue = $this->request->getVar("filter[$filterKey]");
            if ($filterValue !== null && $filterValue !== '') {
                if (in_array($filterKey, ['activity_count_min', 'activity_count_max'])) {
                    $conditions[] = $dbColumn;
                    $params[] = (int) $filterValue;
                } elseif (in_array($filterKey, ['address', 'full_name', 'job', 'religion'])) {
                    $conditions[] = "$dbColumn LIKE ?";
                    $params[] = "%$filterValue%";
                } else {
                    $conditions[] = "$dbColumn = ?";
                    $params[] = $filterValue;
                }
            }
        }

        // Add conditions to query
        if (!empty($conditions)) {
            $query .= ' AND ' . implode(' AND ', $conditions);
        }

        // Order by activity count
        $query .= " ORDER BY activity_count DESC, m.member_username ASC";

        // Pagination
        if ($paginate) {
            $countQuery = "SELECT COUNT(*) as total FROM ($query) as subquery";
            $total = $db->query($countQuery, $params)->getRow()->total;

            $query .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
        }

        // Execute query
        $result = $db->query($query, $params)->getResultArray();



        $result = array_map(function ($book) {
            // Simpan 'id' terlebih dahulu
            $id = $book['member_id'];
            unset($book['member_id']);  // Hapus 'book_id'

            // Letakkan 'id' di urutan teratas
            $book = array_merge(['id' => $id], $book);

            return $book;
        }, $result);

        // Pagination response
        $paginationResponse = [
            'total_data' => $paginate ? (int) $total : count($result),
            'total_pages' => $paginate ? (int) ceil($total / $limit) : 1,
            'prev' => ($page > 1) ? $page - 1 : null,
            'page' => $page,
            'next' => $paginate && ($page < ceil($total / $limit)) ? $page + 1 : null,
            'detail' => $paginate ? range(max(1, $page - 2), min(ceil($total / $limit), $page + 2)) : [],
            'start' => $paginate ? ($page - 1) * $limit + 1 : 1,
            'end' => $paginate ? min($page * $limit, $total) : count($result),
        ];

        return $this->respondWithSuccess('Behasil mengembalikan data report.', [
            'data' => $result,
            'pagination' => $paginate ? $paginationResponse : (object) [],
        ]);
    }


    public function inactive_users()
    {
        $db = Database::connect();

        // Parameters from query string
        $limit = (int) ($this->request->getVar('limit') ?? 10);
        $page = (int) ($this->request->getVar('page') ?? 1);
        $search = $this->request->getVar('search');
        $paginate = $this->request->getVar('pagination') !== 'false';
        $offset = ($page - 1) * $limit;

        // Base query
        $query = "
        SELECT 
        m.member_id,
        m.member_username,
        m.member_email,
        m.member_full_name,
        m.member_address,
        m.member_job,
        m.member_status,
        m.member_religion,
        m.member_barcode,
        m.member_gender,
        (SELECT COUNT(*) FROM loan WHERE loan_member_id = m.member_id AND loan_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as activity_count
    FROM member m
    WHERE (SELECT COUNT(*) FROM loan WHERE loan_member_id = m.member_id AND loan_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) = 0";

        $conditions = [];
        $params = [];

        // Search conditions
        if ($search) {
            $searchFields = ['m.member_username', 'm.member_email', 'm.member_full_name', 'm.member_address', 'm.member_job', 'm.member_religion', 'm.member_barcode'];
            $searchConditions = array_map(fn($field) => "$field LIKE ?", $searchFields);
            $conditions[] = '(' . implode(' OR ', $searchConditions) . ')';
            $params = array_merge($params, array_fill(0, count($searchFields), "%$search%"));
        }

        // Additional filters
        $filters = [
            'id' => 'm.member_id',
            'username' => 'm.member_username',
            'email' => 'm.member_email',
            'full_name' => 'm.member_full_name',
            'address' => 'm.member_address',
            'job' => 'm.member_job',
            'status' => 'm.member_status',
            'religion' => 'm.member_religion',
            'barcode' => 'm.member_barcode',
            'gender' => 'm.member_gender',
        ];

        foreach ($filters as $filterKey => $dbColumn) {
            $filterValue = $this->request->getVar("filter[$filterKey]");
            if ($filterValue !== null && $filterValue !== '') {
                if (in_array($filterKey, ['address', 'full_name', 'job', 'religion'])) {
                    $conditions[] = "$dbColumn LIKE ?";
                    $params[] = "%$filterValue%";
                } else {
                    $conditions[] = "$dbColumn = ?";
                    $params[] = $filterValue;
                }
            }
        }

        // Add conditions to query
        if (!empty($conditions)) {
            $query .= ' AND ' . implode(' AND ', $conditions);
        }

        // Order by member username
        $query .= " ORDER BY m.member_username ASC";

        // Pagination
        if ($paginate) {
            $countQuery = "SELECT COUNT(*) as total FROM ($query) as subquery";
            $total = $db->query($countQuery, $params)->getRow()->total;

            $query .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
        }

        // Execute query
        $result = $db->query($query, $params)->getResultArray();

        // Pagination response
        $paginationResponse = [
            'total_data' => $paginate ? (int) $total : count($result),
            'total_pages' => $paginate ? (int) ceil($total / $limit) : 1,
            'prev' => ($page > 1) ? $page - 1 : null,
            'page' => $page,
            'next' => $paginate && ($page < ceil($total / $limit)) ? $page + 1 : null,
            'detail' => $paginate ? range(max(1, $page - 2), min(ceil($total / $limit), $page + 2)) : [],
            'start' => $paginate ? ($page - 1) * $limit + 1 : 1,
            'end' => $paginate ? min($page * $limit, $total) : count($result),
        ];

        return $this->respondWithSuccess('Behasil mengembalikan data report.', [
            'data' => $result,
            'pagination' => $paginate ? $paginationResponse : (object) [],
        ]);
    }

    public function detailed_member_activity()
    {
        $db = Database::connect();

        // Parameters from query string
        $limit = (int) ($this->request->getVar('limit') ?? 10);
        $page = (int) ($this->request->getVar('page') ?? 1);
        $search = $this->request->getVar('search');
        $paginate = $this->request->getVar('pagination') !== 'false';
        $offset = ($page - 1) * $limit;

        // Base query
        $query = "
    SELECT 
        m.member_id,
        m.member_username,
        m.member_email,
        m.member_full_name,
        m.member_address,
        m.member_job,
        m.member_status,
        m.member_religion,
        m.member_barcode,
        m.member_gender,
        (SELECT COUNT(*) FROM loan WHERE loan_member_id = m.member_id) as activity_count
    FROM member m
    WHERE 1=1";

        $conditions = [];
        $params = [];

        // Search conditions
        if ($search) {
            $searchFields = ['m.member_username', 'm.member_email', 'm.member_full_name', 'm.member_address', 'm.member_job', 'm.member_religion', 'm.member_barcode'];
            $searchConditions = array_map(fn($field) => "$field LIKE ?", $searchFields);
            $conditions[] = '(' . implode(' OR ', $searchConditions) . ')';
            $params = array_merge($params, array_fill(0, count($searchFields), "%$search%"));
        }

        // Additional filters
        $filters = [
            'member_id' => 'm.member_id',
            'username' => 'm.member_username',
            'email' => 'm.member_email',
            'full_name' => 'm.member_full_name',
            'address' => 'm.member_address',
            'job' => 'm.member_job',
            'status' => 'm.member_status',
            'religion' => 'm.member_religion',
            'barcode' => 'm.member_barcode',
            'gender' => 'm.member_gender',
            'activity_count_min' => '(SELECT COUNT(*) FROM loan WHERE loan_member_id = m.member_id) >= ?',
            'activity_count_max' => '(SELECT COUNT(*) FROM loan WHERE loan_member_id = m.member_id) <= ?',
        ];

        foreach ($filters as $filterKey => $dbColumn) {
            $filterValue = $this->request->getVar("filter[$filterKey]");
            if ($filterValue !== null && $filterValue !== '') {
                if (in_array($filterKey, ['activity_count_min', 'activity_count_max'])) {
                    $conditions[] = $dbColumn;
                    $params[] = (int) $filterValue;
                } elseif (in_array($filterKey, ['address', 'full_name', 'job', 'religion'])) {
                    $conditions[] = "$dbColumn LIKE ?";
                    $params[] = "%$filterValue%";
                } else {
                    $conditions[] = "$dbColumn = ?";
                    $params[] = $filterValue;
                }
            }
        }

        // Add conditions to query
        if (!empty($conditions)) {
            $query .= ' AND ' . implode(' AND ', $conditions);
        }

        // Order by member_id
        $query .= " ORDER BY m.member_id ASC";

        // Pagination
        if ($paginate) {
            $countQuery = "SELECT COUNT(*) as total FROM ($query) as subquery";
            $total = $db->query($countQuery, $params)->getRow()->total;

            $query .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
        }

        // Execute query
        $members = $db->query($query, $params)->getResultArray();

        // Fetch loan details for each member
        $detailedMembers = [];
        foreach ($members as $member) {
            $memberId = $member['member_id'];

            // Fetch loan details
            $loansQuery = "
        SELECT 
            loan_id, 
            loan_transaction_code, 
            loan_date, 
            loan_member_username, 
            loan_member_email, 
            loan_member_full_name, 
            loan_member_address 
        FROM loan 
        WHERE loan_member_id = ?
        ORDER BY loan_date DESC
        LIMIT 5"; // Limiting to last 5 loans for performance
            $loans = $db->query($loansQuery, [$memberId])->getResultArray();

            $member['loans'] = $loans;
            $detailedMembers[] = $member;
        }

        // Pagination response
        $paginationResponse = [
            'total_data' => $paginate ? (int) $total : count($detailedMembers),
            'total_pages' => $paginate ? (int) ceil($total / $limit) : 1,
            'prev' => ($page > 1) ? $page - 1 : null,
            'page' => $page,
            'next' => $paginate && ($page < ceil($total / $limit)) ? $page + 1 : null,
            'detail' => $paginate ? range(max(1, $page - 2), min(ceil($total / $limit), $page + 2)) : [],
            'start' => $paginate ? ($page - 1) * $limit + 1 : 1,
            'end' => $paginate ? min($page * $limit, $total) : count($detailedMembers),
        ];

        return $this->respondWithSuccess('Behasil mengembalikan data report.', [
            'data' => $detailedMembers,
            'pagination' => $paginate ? $paginationResponse : (object) [],
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
        ];

        return $this->respondWithSuccess('Behasil mengembalikan data statistik', $result);
    }

}
