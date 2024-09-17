<?php

namespace App\Controllers;

class Home extends CoreController
{
    public function index()
    {
        $message = "Welcome to the Library API v2. Use the endpoints provided to interact with the library's resources. enjoyy broo :)";

        return $this->respondWithSuccess($message, null, 200);
    }
}