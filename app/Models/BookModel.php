<?php

namespace App\Models;

use CodeIgniter\Model;

class AuthorModel extends Model
{
    protected $table = 'catalog_buku';
    protected $primaryKey = 'book_id';
    protected $allowedFields = ['title', 'publisher_id', 'publication_year', 'isbn', 'stock_quantity'];
    protected $useTimestamps = true;
}
