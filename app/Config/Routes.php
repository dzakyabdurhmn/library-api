<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

$routes->group('', ['filter' => 'authToken'], function ($routes) {


    // Route untuk fitur register, login, dan logout
    $routes->post('/admin/auth/register', 'AuthController::register');

    $routes->post('auth/login', 'AuthController::login');
    $routes->post('auth/logout', 'AuthController::logout');
    $routes->delete('auth/delete_account/(:num)', 'AuthController::delete_account/$1');
    $routes->put('auth/edit_account/(:num)', 'AuthController::edit_account/$1');
    $routes->get('auth/', 'AuthController::get_all_users'); // Mendapatkan semua pengguna
    $routes->get('auth/detail', 'AuthController::get_user_by_id'); // Mendapatkan pengguna berdasarkan ID

    $routes->post('forgot-password', 'AuthController::sendResetPassword');
    $routes->post('reset-password', 'AuthController::resetPassword');



    $routes->group('members/', function ($routes) {
        $routes->get('/', 'MemberController::index'); // Mendapatkan semua member
        $routes->get('detail', 'MemberController::get_detail'); // Mendapatkan detail member berdasarkan ID
        $routes->post('/', 'MemberController::create'); // Menambahkan member baru
        $routes->put('(:num)', 'MemberController::update/$1'); // Memperbarui member berdasarkan ID
        $routes->delete('(:num)', 'MemberController::delete/$1'); // Menghapus member berdasarkan ID
    });


    $routes->group('books/', function ($routes) {
        $routes->get('/', 'BookController::index'); // Mendapatkan semua buku dengan pagination, search, dan filter
        $routes->get('detail', 'BookController::get_detail'); // Mendapatkan buku berdasarkan ID
        $routes->post('/', 'BookController::create'); // Menambahkan buku baru
        $routes->put('(:num)', 'BookController::update/$1'); // Memperbarui buku berdasarkan ID
        $routes->delete('(:num)', 'BookController::delete/$1'); // Menghapus buku berdasarkan ID
    });

    $routes->get('authors/detail', 'AuthorController::get_detail'); // Mendapatkan penulis berdasarkan ID

    $routes->group('authors', function ($routes) {
        $routes->get('/', 'AuthorController::index'); // Mendapatkan semua penulis dengan pagination, search, dan filter
        $routes->get('/detail', 'AuthorController::get_detail'); // Mendapatkan penulis berdasarkan ID
        $routes->post('/', 'AuthorController::create'); // Menambahkan penulis baru
        $routes->put('(:num)', 'AuthorController::update/$1'); // Memperbarui penulis berdasarkan ID
        $routes->delete('(:num)', 'AuthorController::delete/$1'); // Menghapus penulis berdasarkan ID
    });

    $routes->group('publishers/', function ($routes) {
        $routes->get('/', 'PublisherController::index'); // Mendapatkan semua penerbit dengan pagination, search, dan filter
        $routes->get('detail', 'PublisherController::get_detail'); // Mendapatkan penerbit berdasarkan ID
        $routes->post('/', 'PublisherController::create'); // Menambahkan penerbit baru
        $routes->put('(:num)', 'PublisherController::update/$1'); // Memperbarui penerbit berdasarkan ID
        $routes->delete('(:num)', 'PublisherController::delete/$1'); // Menghapus penerbit berdasarkan ID
    });



    $routes->group('transaction/', function ($routes) {
        $routes->get('', 'LoanController::get_all_borrow');
        $routes->get('detail', 'LoanController::get_detail_loan');
        $routes->post('deport', 'LoanController::deport');
        $routes->post('borrow', 'LoanController::borrow_book');
    });

    $routes->group('report', function ($routes) {
        $routes->get('most-borrowed-books', 'ReportController::most_borrowed_books');
        $routes->get('least-borrowed-books', 'ReportController::least_borrowed_books');
        $routes->get('broken-missing-books', 'ReportController::broken_missing_books');
        $routes->get('most-active-users', 'ReportController::most_active_users');
        $routes->get('inactive-users', 'ReportController::inactive_users');
        $routes->get('active-admins', 'ReportController::active_admins');
        $routes->get('detailed-member-activity', 'ReportController::detailed_member_activity');
        $routes->get('detailed-borrowed-books', 'ReportController::detailed_borrowed_books');
        $routes->get('count-books-status', 'ReportController::count_books_status');

    });


});

