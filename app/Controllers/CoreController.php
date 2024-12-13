<?php

namespace App\Controllers;

use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\RESTful\ResourceController;
use Config\Database;

class CoreController extends ResourceController
{
    protected $format = 'json';

    protected $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }



    protected function respondWithSuccess($message, $result = null, $code = 200)
    {
        $response = [
            'status' => $code,
            'message' => $message,
            'error' => "",
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


    public function paginate($table, $columns, $search = null, $filters = [], $sort = null, $limit = 10, $page = 1, $enablePagination = true)
    {
        $offset = ($page - 1) * $limit;

        $query = "SELECT $columns FROM $table";
        $conditions = [];
        $params = [];

        if ($search) {
            $conditions[] = "($columns LIKE ?)";
            $searchTerm = "%" . $search . "%";
            $params[] = $searchTerm;
        }

        // Mapping filter keys to column names
        $filterMapping = array_combine(array_keys($filters), array_keys($filters));

        foreach ($filters as $key => $value) {
            if (!empty($value) && array_key_exists($key, $filterMapping)) {
                $conditions[] = "{$filterMapping[$key]} = ?";
                $params[] = $value;
            }
        }

        if (count($conditions) > 0) {
            $query .= " WHERE " . implode(" AND ", $conditions);
        }

        if ($enablePagination) {
            $query .= " LIMIT ? OFFSET ?";
            $params[] = (int) $limit;
            $params[] = (int) $offset;
        }

        try {
            $results = $this->db->query($query, $params)->getResultArray();

            $pagination = new \stdClass();
            if ($enablePagination) {
                $totalQuery = "SELECT COUNT(*) as total FROM $table";
                if (!empty($conditions)) {
                    $totalQuery .= " WHERE " . implode(" AND ", $conditions);
                }
                $total = $this->db->query($totalQuery, array_slice($params, 0, count($params) - 2))->getRow()->total;

                $jumlah_page = ceil($total / $limit);
                $pagination = [
                    "total_data" => (int) $total,
                    "jumlah_page" => (int) $jumlah_page,
                    "prev" => $page > 1 ? $page - 1 : null,
                    "page" => (int) $page,
                    "next" => $page < $jumlah_page ? $page + 1 : null,
                    "start" => ($page - 1) * $limit + 1,
                    "end" => min($page * $limit, $total),
                    "detail" => range(max(1, $page - 2), min($jumlah_page, $page + 2)),
                ];
            }

            return [
                "data" => $results,
                "pagination" => $pagination,
            ];
        } catch (DatabaseException $e) {
            throw new \RuntimeException("Terdapat kesalahan di server: " . $e->getMessage());
        }
    }


}

