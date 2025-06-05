<?php

namespace App\Features;

class Payroll
{
    public $name = 'payroll';

    public function resolve(): mixed
    {
        return true; // Ubah ke logic lain jika ingin enable/disable fitur secara dinamis
    }
}
