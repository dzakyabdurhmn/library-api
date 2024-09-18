<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');


// SUPERADMIN

$routes->post('/superadmin/auth/register', 'AuthController::register');




$routes->post('/auth/login', 'AuthController::login');
$routes->post('/auth/logout', 'AuthController::logout');  // Pastikan rute ini benar