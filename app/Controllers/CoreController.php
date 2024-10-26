<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;

class CoreController extends ResourceController
{
    protected $format = 'json';

    protected function respondWithSuccess($message, $result = null, $code = 200)
    {
        $response = [
            'status' => $code,
            'message' => $message,
        ];

        $response['result'] = $result == null ? (object) ['data' => (object) []] : $result;



        return $this->respond($response, $code);
    }

    protected function respondWithValidationError($message, $errors = [], $code = 412)
    {
        $response = [
            'status' => $code,
            'message' => $message,
            'error' => 'error_validation'
        ];

        if (!empty($errors)) {
            $response['result'] = [
                'data' => (object) $errors // Mengubah $errors menjadi objek
            ];
        }


        return $this->respond($response, $code);
    }

    protected function respondWithNotFound($message, $code = 404)
    {
        return $this->respond([
            'status' => $code,
            'message' => $message
        ], $code);
    }

    protected function respondWithUnauthorized($message, $code = 401)
    {
        return $this->respond([
            'status' => $code,
            'message' => $message
        ], $code);
    }

    protected function respondWithDeleted($message, $code = 500)
    {
        return $this->respond([
            'status' => $code,
            'message' => $message
        ], $code);
    }

    protected function respondWithError($message, $data = null, $statusCode = 400)
    {
        return $this->respond([
            'status' => 'error',
            'message' => $message,
        ], $statusCode);
    }

}

