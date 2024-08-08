<?php

namespace App\Controllers;

use CodeIgniter\Controller;

class ReportController extends Controller
{

public function get_report()
    {
        $db = db_connect();

        // Query untuk mendapatkan laporan tanpa filter
        $reportQuery = "
            SELECT 
                r.report_id, 
                r.user_id, 
                r.username, 
                r.email, 
                r.full_name, 
                r.address, 
                rb.book_id,
                rb.title,
                rb.publisher_id,
                rb.publication_year,
                rb.isbn,
                rb.stock_quantity,
                rb.author_id
            FROM report r
            JOIN report_books rb ON r.report_id = rb.report_id
        ";

        $result = $db->query($reportQuery)->getResultArray();

        // Organize the result into the desired format
        $reports = [];
        foreach ($result as $row) {
            $reportId = $row['report_id'];
            if (!isset($reports[$reportId])) {
                $reports[$reportId] = [
                    'report_id' => $row['report_id'],
                    'user_id' => $row['user_id'],
                    'username' => $row['username'],
                    'email' => $row['email'],
                    'full_name' => $row['full_name'],
                    'address' => $row['address'],
                    'books' => []
                ];
            }
            
            $reports[$reportId]['books'][] = [
                'book_id' => $row['book_id'],
                'title' => $row['title'],
                'publisher_id' => $row['publisher_id'],
                'publication_year' => $row['publication_year'],
                'isbn' => $row['isbn'],
                'stock_quantity' => $row['stock_quantity'],
                'author_id' => $row['author_id']
            ];
        }

        if (empty($reports)) {
            return $this->response->setJSON([
                'status' => 404,
                'message' => 'No reports found'
            ])->setStatusCode(404);
        }

        return $this->response->setJSON([
            'status' => 200,
            'message' => 'Reports retrieved successfully',
            'data' => array_values($reports)
        ])->setStatusCode(200);
    }

    public function getReportByUser($user_id)
    {
        $db = db_connect();

        $reportQuery = "
            SELECT 
                r.report_id, 
                r.user_id, 
                r.username, 
                r.email, 
                r.full_name, 
                r.address, 
                rb.book_id,
                rb.title,
                rb.publisher_id,
                rb.publication_year,
                rb.isbn,
                rb.stock_quantity,
                rb.author_id
            FROM report r
            JOIN report_books rb ON r.report_id = rb.report_id
            WHERE r.user_id = ?
        ";

        $result = $db->query($reportQuery, [$user_id])->getResultArray();

        // Organize the result into the desired format
        $reports = [];
        foreach ($result as $row) {
            $reportId = $row['report_id'];
            if (!isset($reports[$reportId])) {
                $reports[$reportId] = [
                    'report_id' => $row['report_id'],
                    'user_id' => $row['user_id'],
                    'username' => $row['username'],
                    'email' => $row['email'],
                    'full_name' => $row['full_name'],
                    'address' => $row['address'],
                    'books' => []
                ];
            }
            
            $reports[$reportId]['books'][] = [
                'book_id' => $row['book_id'],
                'title' => $row['title'],
                'publisher_id' => $row['publisher_id'],
                'publication_year' => $row['publication_year'],
                'isbn' => $row['isbn'],
                'stock_quantity' => $row['stock_quantity'],
                'author_id' => $row['author_id']
            ];
        }

        if (empty($reports)) {
            return $this->response->setJSON([
                'status' => 404,
                'message' => 'No reports found for this user'
            ])->setStatusCode(404);
        }

        return $this->response->setJSON([
            'status' => 200,
            'message' => 'Reports retrieved successfully',
            'data' => array_values($reports)
        ])->setStatusCode(200);
    }

    public function getReportByBook($book_id)
    {
        $db = db_connect();

        $reportQuery = "
            SELECT 
                r.report_id, 
                r.user_id, 
                r.username, 
                r.email, 
                r.full_name, 
                r.address, 
                rb.book_id,
                rb.title,
                rb.publisher_id,
                rb.publication_year,
                rb.isbn,
                rb.stock_quantity,
                rb.author_id
            FROM report r
            JOIN report_books rb ON r.report_id = rb.report_id
            WHERE rb.book_id = ?
        ";

        $result = $db->query($reportQuery, [$book_id])->getResultArray();

        // Organize the result into the desired format
        $reports = [];
        foreach ($result as $row) {
            $reportId = $row['report_id'];
            if (!isset($reports[$reportId])) {
                $reports[$reportId] = [
                    'report_id' => $row['report_id'],
                    'user_id' => $row['user_id'],
                    'username' => $row['username'],
                    'email' => $row['email'],
                    'full_name' => $row['full_name'],
                    'address' => $row['address'],
                    'books' => []
                ];
            }
            
            $reports[$reportId]['books'][] = [
                'book_id' => $row['book_id'],
                'title' => $row['title'],
                'publisher_id' => $row['publisher_id'],
                'publication_year' => $row['publication_year'],
                'isbn' => $row['isbn'],
                'stock_quantity' => $row['stock_quantity'],
                'author_id' => $row['author_id']
            ];
        }

        if (empty($reports)) {
            return $this->response->setJSON([
                'status' => 404,
                'message' => 'No reports found for this book'
            ])->setStatusCode(404);
        }

        return $this->response->setJSON([
            'status' => 200,
            'message' => 'Reports retrieved successfully',
            'data' => array_values($reports)
        ])->setStatusCode(200);
    }


    public function generate_report($user_id, $book_id)
    {
        $db = db_connect();

        // Query untuk mendapatkan data user
        $userQuery = "
            SELECT 
                user_id, 
                username, 
                email, 
                COALESCE(full_name, '') as full_name, 
                COALESCE(address, '') as address 
            FROM member
            WHERE user_id = ?
        ";

        $userData = $db->query($userQuery, [$user_id])->getRowArray();

        if ($userData) {
            // Insert atau Update data ke dalam tabel report
            $reportQuery = "
                INSERT INTO report (
                    user_id, username, email, full_name, address
                ) VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    username = VALUES(username), 
                    email = VALUES(email), 
                    full_name = VALUES(full_name), 
                    address = VALUES(address)
            ";

            $db->query($reportQuery, [
                $userData['user_id'],
                $userData['username'],
                $userData['email'],
                $userData['full_name'],
                $userData['address']
            ]);

            // Mendapatkan report_id dari report yang baru diinsert
            $reportIdQuery = "SELECT report_id FROM report WHERE user_id = ?";
            $reportId = $db->query($reportIdQuery, [$user_id])->getRowArray()['report_id'];

            // Query untuk mendapatkan data buku
            $bookQuery = "
                SELECT 
                    book_id, 
                    title, 
                    publisher_id, 
                    publication_year, 
                    isbn, 
                    stock_quantity, 
                    author_id
                FROM catalog_books
                WHERE book_id = ?
            ";

            $bookData = $db->query($bookQuery, [$book_id])->getRowArray();

            if ($bookData) {
                // Insert data ke dalam tabel report_books
                $reportBookQuery = "
                    INSERT INTO report_books (
                        report_id, book_id, title, publisher_id, publication_year, isbn, stock_quantity, author_id
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ";

                $db->query($reportBookQuery, [
                    $reportId,
                    $bookData['book_id'],
                    $bookData['title'],
                    $bookData['publisher_id'],
                    $bookData['publication_year'],
                    $bookData['isbn'],
                    $bookData['stock_quantity'],
                    $bookData['author_id']
                ]);

                // Return data sebagai array, bukan respon HTTP
                return [
                    'status' => 201,
                    'message' => 'Report generated successfully',
                    'data' => array_merge($userData, $bookData)
                ];
            } else {
                // Return error sebagai array
                return [
                    'status' => 404,
                    'message' => 'Book not found'
                ];
            }
        } else {
            // Return error sebagai array
            return [
                'status' => 404,
                'message' => 'User not found'
            ];
        }
    }
}


// buku yang paling banyak di pinjem, buku yang rusak berapa , user yang paling banyak minjem