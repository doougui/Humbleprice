<?php

namespace App\Models;

use App\Core\Table;

class Role extends Table
{
    public function __construct()
    {
        parent::__construct();
        $this->table = "role";
    }
}