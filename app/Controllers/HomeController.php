<?php

namespace App\Controllers;

class HomeController extends CoreController
{
    public function index()
    {
        $message = "Welcome to the Library API. Use the endpoints provided to interact with the library's resources. enjoyy :)";

        return $this->respondWithSuccess($message, null, 200);
    }
}

// https://chatgpt.com/c/c5f87177-0290-4207-8fe1-89641af99897





// #########################
// # Model - mirip mirip ORM
// #########################

// namespace App\Controllers;

// use App\Models\UserModel;
// use CodeIgniter\RESTful\ResourceController;

// class AuthController extends ResourceController
// {
//     protected $modelName = 'App\Models\UserModel';
//     protected $format    = 'json';

//     public function register()
//     {
//         $rules = [
//             'username' => 'required|min_length[3]|max_length[100]|is_unique[users.username]',
//             'email'    => 'required|valid_email|is_unique[users.email]',
//             'password' => 'required|min_length[8]'
//         ];

//         $response = [
//             'status' =>  200,
//             'message' => 'user alredy register',
//         ];


//         if (!$this->validate($rules)) {
//             return $this->failValidationErrors($this->validator->getErrors());
//         }

     
 

//         $data = [
//             'username' => $this->request->getVar('username'),
//             'email'    => $this->request->getVar('email'),
//             'password' => password_hash($this->request->getVar('password'), PASSWORD_DEFAULT)
//         ];

//         $this->model->save($data);

//         $response = [
//             'status' =>  200,
//             'message' => 'User registered successfully',
//         ];

//         // Kembalikan respons dengan status code 201
//         return $this->respondCreated($response);    }

//     public function login()
//     {
//         $rules = [
//             'username' => 'required',
//             'password' => 'required'
//         ];

//         if (!$this->validate($rules)) {
//             return $this->failValidationErrors($this->validator->getErrors());
//         }

//         $user = $this->model->where('username', $this->request->getVar('username'))->first();



//         $response = [
//             'status' =>  401,
//             'message' => 'Invalid login credentials',
//         ];

//         if (!$user || !password_verify($this->request->getVar('password'), $user['password'])) {
//             return $this->respond($response);
//         }

//         $response = [
//             'status' =>  200,
//             'message' => 'Login successful',
//         ];

//         return $this->respond($response);
//     }
// }


// #######################
// # Query builder
// ######################

// namespace App\Controllers;

// use App\Models\UserModel;
// use CodeIgniter\RESTful\ResourceController;
// use CodeIgniter\Database\Query;

// class AuthController extends ResourceController
// {
//     protected $modelName = 'App\Models\UserModel';
//     protected $format    = 'json';

//     public function register()
//     {
//         $rules = [
//             'username' => 'required|min_length[3]|max_length[100]|is_unique[users.username]',
//             'email'    => 'required|valid_email|is_unique[users.email]',
//             'password' => 'required|min_length[8]'
//         ];

//         if (!$this->validate($rules)) {
//                 $this->failValidationErrors($this->validator->getErrors());
//         }

//         $db = \Config\Database::connect();
//         $builder = $db->table('users');

//         $data = [
//             'username' => $this->request->getVar('username'),
//             'email'    => $this->request->getVar('email'),
//             'password' => password_hash($this->request->getVar('password'), PASSWORD_DEFAULT)
//         ];

//         $builder->insert($data);

//         $response = [
//             'status' =>  201,
//             'message' => 'User registered successfully',
//         ];

//         return $this->respondCreated($response);
//     }

//     public function login()
//     {
//         $rules = [
//             'username' => 'required',
//             'password' => 'required'
//         ];

//         if (!$this->validate($rules)) {
//             return $this->failValidationErrors($this->validator->getErrors());
//         }

//         $db = \Config\Database::connect();
//         $builder = $db->table('users');
//         $user = $builder->where('username', $this->request->getVar('username'))->get()->getRowArray();

//         $response = [
//             'status' =>  401,
//             'message' => 'Invalid login credentials',
//         ];

//         if (!$user || !password_verify($this->request->getVar('password'), $user['password'])) {
//             return $this->respond($response);
//         }

//         $response = [
//             'status' =>  200,
//             'message' => 'Login successful',
//         ];

//         return $this->respond($response);
//     }
// }


// #########################
// # Raw query 😎
// #########################
