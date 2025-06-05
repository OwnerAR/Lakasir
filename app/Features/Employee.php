<?php

namespace App\Features;

class Employee
{
    public $name = 'employee';

    public function resolve(): mixed
    {
        return true; // Ubah ke logic lain jika ingin enable/disable fitur secara dinamis
    }
}
