<?php

namespace App\Controllers;

class LiblaryReport extends CoreController
{
    public function generate_report()
    {
        $db = db_connect();

        // Buku yang paling banyak dipinjam
        $mostBorrowedBooksQuery = "
            SELECT c.title, c.isbn, c.publication_year, c.stock_quantity, 
                   p.publisher_name, p.address as publisher_address, p.phone as publisher_phone, p.email as publisher_email,
                   a.author_name, a.biography as author_biography, COUNT(l.book_id) as borrow_count
            FROM loan l
            JOIN catalog_books c ON l.book_id = c.book_id
            LEFT JOIN publisher p ON c.publisher_id = p.publisher_id
            LEFT JOIN author a ON c.author_id = a.author_id
            GROUP BY l.book_id
            ORDER BY borrow_count DESC
        ";
        $mostBorrowedBooks = $db->query($mostBorrowedBooksQuery)->getResultArray();

        // Buku yang paling sedikit dipinjam
        $leastBorrowedBooksQuery = "
            SELECT c.title, c.isbn, c.publication_year, c.stock_quantity, 
                   p.publisher_name, p.address as publisher_address, p.phone as publisher_phone, p.email as publisher_email,
                   a.author_name, a.biography as author_biography, COUNT(l.book_id) as borrow_count
            FROM loan l
            JOIN catalog_books c ON l.book_id = c.book_id
            LEFT JOIN publisher p ON c.publisher_id = p.publisher_id
            LEFT JOIN author a ON c.author_id = a.author_id
            GROUP BY l.book_id
            ORDER BY borrow_count ASC
        ";
        $leastBorrowedBooks = $db->query($leastBorrowedBooksQuery)->getResultArray();

        // Buku yang rusak
        $brokenBooksQuery = "
            SELECT c.title, c.isbn, c.publication_year, c.stock_quantity, 
                   p.publisher_name, p.address as publisher_address, p.phone as publisher_phone, p.email as publisher_email,
                   a.author_name, a.biography as author_biography, COUNT(l.book_id) as broken_count
            FROM loan l
            JOIN catalog_books c ON l.book_id = c.book_id
            LEFT JOIN publisher p ON c.publisher_id = p.publisher_id
            LEFT JOIN author a ON c.author_id = a.author_id
            WHERE l.status = 'Broken'
            GROUP BY l.book_id
        ";
        $brokenBooks = $db->query($brokenBooksQuery)->getResultArray();

        // Buku yang hilang
        $missingBooksQuery = "
            SELECT c.title, c.isbn, c.publication_year, c.stock_quantity, 
                   p.publisher_name, p.address as publisher_address, p.phone as publisher_phone, p.email as publisher_email,
                   a.author_name, a.biography as author_biography, COUNT(l.book_id) as missing_count
            FROM loan l
            JOIN catalog_books c ON l.book_id = c.book_id
            LEFT JOIN publisher p ON c.publisher_id = p.publisher_id
            LEFT JOIN author a ON c.author_id = a.author_id
            WHERE l.status = 'Missing'
            GROUP BY l.book_id
        ";
        $missingBooks = $db->query($missingBooksQuery)->getResultArray();

        // User yang paling banyak minjem
        $mostActiveUsersQuery = "
            SELECT m.username, m.email, m.full_name, m.address, COUNT(l.user_id) as borrow_count
            FROM loan l
            JOIN member m ON l.user_id = m.user_id
            GROUP BY l.user_id
            ORDER BY borrow_count DESC
        ";
        $mostActiveUsers = $db->query($mostActiveUsersQuery)->getResultArray();

        // User yang sudah tidak aktif 30 hari
        $inactiveUsersQuery = "
            SELECT m.username, m.email, m.full_name, m.address, MAX(l.loan_date) as last_loan_date, COUNT(l.user_id) as borrow_count
            FROM member m
            LEFT JOIN loan l ON m.user_id = l.user_id
            GROUP BY m.user_id
            HAVING last_loan_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY) OR last_loan_date IS NULL
        ";
        $inactiveUsers = $db->query($inactiveUsersQuery)->getResultArray();

        // Report summary
        $report = [
            'most_borrowed_books' => $mostBorrowedBooks,
            'least_borrowed_books' => $leastBorrowedBooks,
            'broken_books' => $brokenBooks,
            'missing_books' => $missingBooks,
            'most_active_users' => $mostActiveUsers,
            'inactive_users' => $inactiveUsers,
        ];

        return $this->respondWithSuccess('Report generated successfully', $report);
    }
}
