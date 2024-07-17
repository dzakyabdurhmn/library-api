<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

// AUTH
// $routes->post('register', 'AuthController::register');
// $routes->post('login', 'AuthController::login');

$routes->group('member', ['namespace' => 'App\Controllers'], function($routes) {
    $routes->get('', 'MemberController::index');  
    $routes->post('create', 'MemberController::create');            
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
    $routes->get('avalible', 'BookController::avalible_book');  
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

$routes->group('loan', ['namespace' => 'App\Controllers'], function($routes) {
    $routes->post('borrow', 'LoanController::borrow_book');            
    $routes->post('deport', 'LoanController::return_book');  
});



