<?php

namespace App\Controllers;

class ReportController extends CoreController
{
    protected $db;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    // Laporan buku yang paling banyak dipinjam
    public function most_borrowed_books()
    {
        // Ambil data dari loan_book
        $bookQuery = "SELECT book_id, book_title, COUNT(*) as borrow_count FROM loan_book GROUP BY book_id, book_title ORDER BY borrow_count DESC";
        $books = $this->db->query($bookQuery)->getResultArray();

        // Untuk setiap buku, ambil detail tambahan dari tabel loan_book
        foreach ($books as &$book) {
            $bookDetailQuery = "SELECT * FROM loan_book WHERE book_id = ? LIMIT 1";
            $bookDetail = $this->db->query($bookDetailQuery, [$book['book_id']])->getRowArray();
            $book = array_merge($book, $bookDetail);
        }

        return $this->respondWithSuccess('Most borrowed books retrieved successfully', $books);
    }

    // Laporan buku yang paling sedikit dipinjam
    public function least_borrowed_books()
    {
        // Ambil data dari loan_book
        $bookQuery = "SELECT book_id, book_title, COUNT(*) as borrow_count FROM loan_book GROUP BY book_id, book_title ORDER BY borrow_count ASC";
        $books = $this->db->query($bookQuery)->getResultArray();

        // Untuk setiap buku, ambil detail tambahan dari tabel loan_book
        foreach ($books as &$book) {
            $bookDetailQuery = "SELECT * FROM loan_book WHERE book_id = ? LIMIT 1";
            $bookDetail = $this->db->query($bookDetailQuery, [$book['book_id']])->getRowArray();
            $book = array_merge($book, $bookDetail);
        }

        return $this->respondWithSuccess('Least borrowed books retrieved successfully', $books);
    }

    // Laporan buku rusak dan hilang
    private function get_books_by_status($status)
    {
        $loanQuery = "SELECT loan_id FROM loan_book WHERE status = ?";
        $loans = $this->db->query($loanQuery, [$status])->getResultArray();

        $result = [];
        foreach ($loans as $loan) {
            $bookQuery = "SELECT * FROM loan_book WHERE loan_id = ?";
            $book = $this->db->query($bookQuery, [$loan['loan_id']])->getRowArray();

            if ($book) {
                $key = $book['book_id'];
                if (isset($result[$key])) {
                    $result[$key][$status . '_count'] += 1;
                } else {
                    $result[$key] = array_merge($book, [$status . '_count' => 1]);
                }
            }
        }

        return array_values($result); // Mengubah array asosiatif menjadi array numerik
    }

    public function broken_books()
    {
        $result = $this->get_books_by_status('Broken');

        return $this->respondWithSuccess('Broken books retrieved successfully', $result);
    }

    public function missing_books()
    {
        $result = $this->get_books_by_status('Missing');

        return $this->respondWithSuccess('Missing books retrieved successfully', $result);
    }

    public function most_active_users()
    {
        // Ambil semua data user dari tabel member
        $userQuery = "SELECT user_id, full_name, email, username, address FROM member";
        $users = $this->db->query($userQuery)->getResultArray();

        $result = [];
        foreach ($users as $user) {
            // Hitung jumlah pinjaman untuk setiap user dari tabel loan_user
            $loanCountQuery = "SELECT COUNT(*) as borrow_count FROM loan_user WHERE user_id = ?";
            $loanCount = $this->db->query($loanCountQuery, [$user['user_id']])->getRowArray();

            // Gabungkan data user dengan borrow_count
            $result[] = array_merge($user, ['borrow_count' => $loanCount['borrow_count']]);
        }

        // Urutkan berdasarkan borrow_count dari yang terbesar
        usort($result, function($a, $b) {
            return $b['borrow_count'] <=> $a['borrow_count'];
        });

        return $this->respondWithSuccess('Most active users retrieved successfully', $result);
    }

    public function inactive_users()
    {
        // Ambil semua data user dari tabel member
        $userQuery = "SELECT user_id, full_name, email, username, address FROM member";
        $users = $this->db->query($userQuery)->getResultArray();

        $result = [];
        foreach ($users as $user) {
            // Hitung jumlah pinjaman untuk setiap user
            $loanCountQuery = "SELECT COUNT(*) as borrow_count FROM loan_user WHERE user_id = ?";
            $loanCount = $this->db->query($loanCountQuery, [$user['user_id']])->getRowArray();

            // Hanya tambahkan user yang memiliki borrow_count = 0
            if ($loanCount['borrow_count'] == 0) {
                $result[] = array_merge($user, ['borrow_count' => 0]);
            }
        }

        return $this->respondWithSuccess('Inactive users retrieved successfully', $result);
    }



    public function get_status_count()
  {
        // Mendapatkan parameter `status` dari query string
        $status = $this->request->getGet('status');

        if($status !== 'Missing' && $status !== 'Borrowed' && status) {
            return $this->respondWithNotFound('Par');
        }

        // Mendapatkan instance dari database
        $db = \Config\Database::connect();

        // Jika status diberikan, hitung hanya berdasarkan status yang dipilih
        if ($status) {
            // Menulis raw query untuk menghitung jumlah buku berdasarkan status tertentu
            $query = $db->query("SELECT COUNT(*) as total FROM loan_book WHERE status = ?", [$status]);
        } else {
            // Menulis raw query untuk menghitung jumlah buku berdasarkan semua status
            $query = $db->query("SELECT status, COUNT(*) as total FROM loan_book GROUP BY status");
        }

        // Mendapatkan hasil query
        $results = $query->getResult();

        // Jika tidak ada data
        if (empty($results)) {
            return $this->respondWithNotFound('No loan book data found.');
        }

        // Jika ada status tertentu yang diminta
        if ($status) {
            $total = $results[0]->total;
            $data = [
                'status' => $status,
                'total' => $total
            ];

            return $this->respondWithSuccess("Count for status '$status' retrieved successfully.", $data);
        }

        // Menyiapkan data untuk semua status
        $data = [];
        foreach ($results as $row) {
            $data[] = [
                'status' => $row->status,
                'total' => $row->total
            ];
        }

        // Merespons dengan data hasil untuk semua status
        return $this->respondWithSuccess('Status counts retrieved successfully.', $data);
    }
}


