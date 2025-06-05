<?php

namespace App\Features;

class Attendance
{
    public $name = 'attendance';

    public function resolve(): mixed
    {
        return true; // Ubah ke logic lain jika ingin enable/disable fitur secara dinamis
    }
}