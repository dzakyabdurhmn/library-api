<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

$routes->group('', ['filter' => 'authToken'], function ($routes) {


    // Route untuk fitur register, login, dan logout
    $routes->post('/superadmin/auth/register', 'AuthController::register');


    $routes->post('/auth/login', 'AuthController::login');
    $routes->post('/auth/logout', 'AuthController::logout');
    $routes->delete('/auth/delete_account/(:num)', 'AuthController::delete_account/$1');
    $routes->put('/auth/edit_account/(:num)', 'AuthController::edit_account/$1');
    $routes->get('auth/', 'AuthController::get_all_users'); // Mendapatkan semua pengguna
    $routes->get('auth/(:num)', 'AuthController::get_user_by_id/$1'); // Mendapatkan pengguna berdasarkan ID

    $routes->post('forgot-password', 'AuthController::sendResetPassword');
    $routes->post('reset-password', 'AuthController::resetPassword');



    $routes->group('members', function ($routes) {
        $routes->get('/', 'MemberController::index'); // Mendapatkan semua member
        $routes->get('(:num)', 'MemberController::show/$1'); // Mendapatkan detail member berdasarkan ID
        $routes->post('/', 'MemberController::create'); // Menambahkan member baru
        $routes->put('(:num)', 'MemberController::update/$1'); // Memperbarui member berdasarkan ID
        $routes->delete('(:num)', 'MemberController::delete/$1'); // Menghapus member berdasarkan ID
    });


    $routes->group('books', function ($routes) {
        $routes->get('/', 'BookController::index'); // Mendapatkan semua buku dengan pagination, search, dan filter
        $routes->get('(:num)', 'BookController::show/$1'); // Mendapatkan buku berdasarkan ID
        $routes->post('/', 'BookController::create'); // Menambahkan buku baru
        $routes->put('(:num)', 'BookController::update/$1'); // Memperbarui buku berdasarkan ID
        $routes->delete('(:num)', 'BookController::delete/$1'); // Menghapus buku berdasarkan ID
    });


    $routes->group('authors', function ($routes) {
        $routes->get('/', 'AuthorController::index'); // Mendapatkan semua penulis dengan pagination, search, dan filter
        $routes->get('(:num)', 'AuthorController::show/$1'); // Mendapatkan penulis berdasarkan ID
        $routes->post('/', 'AuthorController::create'); // Menambahkan penulis baru
        $routes->put('(:num)', 'AuthorController::update/$1'); // Memperbarui penulis berdasarkan ID
        $routes->delete('(:num)', 'AuthorController::delete/$1'); // Menghapus penulis berdasarkan ID
    });

    $routes->group('publishers', function ($routes) {
        $routes->get('/', 'PublisherController::index'); // Mendapatkan semua penerbit dengan pagination, search, dan filter
        $routes->get('(:num)', 'PublisherController::show/$1'); // Mendapatkan penerbit berdasarkan ID
        $routes->post('/', 'PublisherController::create'); // Menambahkan penerbit baru
        $routes->put('(:num)', 'PublisherController::update/$1'); // Memperbarui penerbit berdasarkan ID
        $routes->delete('(:num)', 'PublisherController::delete/$1'); // Menghapus penerbit berdasarkan ID
    });
});

