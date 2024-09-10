<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'HomeController::index');

// AUTH
// $routes->post('register', 'AuthController::register');
// $routes->post('login', 'AuthController::login');

$routes->group('member', ['namespace' => 'App\Controllers'], function($routes) {
    $routes->get('', 'MemberController::index');  
    $routes->post('create', 'MemberController::create');
    $routes->put('(:num)', 'MemberController::update/$1');  // Rute untuk update
    $routes->delete('(:num)', 'MemberController::delete/$1'); // Menambahkan route untuk delete
});


$routes->group('admin', ['namespace' => 'App\Controllers'], function($routes) {
    $routes->post('register', 'AdminController::register');            
    $routes->post('login', 'AdminController::login');  
});

$routes->group('authors', ['namespace' => 'App\Controllers'], function($routes) {
    $routes->get('', 'AuthorController::index');            
    $routes->get('(:num)', 'AuthorController::show/$1');  
    $routes->post('', 'AuthorController::create');         
    $routes->put('(:num)', 'AuthorController::update/$1');   
    $routes->delete('(:num)', 'AuthorController::delete/$1');
});

$routes->group('books', ['namespace' => 'App\Controllers'], function($routes) {
    $routes->get('', 'BookController::index');            
    $routes->get('(:num)', 'BookController::show/$1');
    $routes->post('', 'BookController::create');         
    $routes->put('(:num)', 'BookController::update/$1');   
    $routes->delete('(:num)', 'BookController::delete/$1');
    $routes->put('stock/(:num)', 'BookController::update_stock/$1');    
});

$routes->group('author', ['namespace' => 'App\Controllers'], function($routes) {
    $routes->get('', 'AuthorController::index');            
    $routes->get('(:num)', 'AuthorController::show/$1');  
    $routes->post('', 'AuthorController::create');         
    $routes->put('(:num)', 'AuthorController::update/$1');   
    $routes->delete('(:num)', 'AuthorController::delete/$1');
});

$routes->group('publisher', ['namespace' => 'App\Controllers'], function($routes) {
    $routes->get('', 'PublisherController::index');            
    $routes->get('(:num)', 'PublisherController::show/$1');  
    $routes->post('', 'PublisherController::create');         
    $routes->put('(:num)', 'PublisherController::update/$1');   
    $routes->delete('(:num)', 'PublisherController::delete/$1');
});

$routes->group('loan', ['namespace' => 'App\Controllers'], function($routes) {
    $routes->get('', 'LoanController::index');
    $routes->post('borrow', 'LoanController::borrow_book');            
    $routes->post('deport', 'LoanController::return_book');  
$routes->get('detail', 'LoanController::detail');

});



$routes->group('reports', ['namespace' => 'App\Controllers'], function($routes) {
    $routes->get('most-borrowed-books', 'ReportController::most_borrowed_books');
    $routes->get('least-borrowed-books', 'ReportController::least_borrowed_books');
    $routes->get('broken-books', 'ReportController::broken_books');
    $routes->get('missing-books', 'ReportController::missing_books');
    $routes->get('most-active-users', 'ReportController::most_active_users');
    $routes->get('inactive-users', 'ReportController::inactive_users');
    $routes->get('get-status-count', 'ReportController::get_status_count');

});


