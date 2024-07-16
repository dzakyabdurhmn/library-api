<?php


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


namespace App\Controllers;

use App\Models\UserModel;
use CodeIgniter\RESTful\ResourceController;

class AuthController extends ResourceController
{
    protected $modelName = 'App\Models\UserModel';
    protected $format    = 'json';

    public function register()
    {
        $rules = [
            'username' => 'required|min_length[3]|max_length[100]|is_unique[users.username]',
            'email'    => 'required|valid_email|is_unique[users.email]',
            'password' => 'required|min_length[8]',
            'passconf' => 'required|min_length[8]|matches[password]',
            'full_name'=>'required|min_length[8]',
            'address'=>'required|min_length[8]',
            'phone'=>'required|min_length[10]|numeric'
        ];

        if (!$this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $db = \Config\Database::connect();
        $query = "INSERT INTO users (username, email, password, full_name, address, phone) VALUES (:username:, :email:, :password:,  :full_name:, :address:, :phone:)";
        $params = [
            'username' => $this->request->getVar('username'),
            'email'    => $this->request->getVar('email'),
            'full_name'    => $this->request->getVar('full_name'),
            'address'    => $this->request->getVar('address'),
            'phone'    => $this->request->getVar('phone'),
            'password' => password_hash($this->request->getVar('password'), PASSWORD_DEFAULT)
        ];

        $db->query($query, $params);

        $response = [
            'status' =>  201,
            'message' => 'User registered successfully',
        ];

        return $this->respondCreated($response);
    }

    public function login()
    {
        $rules = [
            'username' => 'required',
            'password' => 'required'
        ];

        if (!$this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $db = \Config\Database::connect();
        $query = "SELECT * FROM users WHERE username = :username:";
        $params = [
            'username' => $this->request->getVar('username')
        ];

        $user = $db->query($query, $params)->getRowArray();

        $response = [
            'status' =>  401,
            'message' => 'Invalid login credentials',
        ];

        if (!$user || !password_verify($this->request->getVar('password'), $user['password'])) {
            return $this->respond($response);
        }

        $response = [
            'status' =>  200,
            'message' => 'Login successful',
        ];

        return $this->respond($response);
    }
}
