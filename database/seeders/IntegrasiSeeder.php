<?php

namespace Database\Seeders;

use App\Models\Tenants\IntegrasiAPI;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IntegrasiAPI extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        IntegrasiAPI::truncate();
        IntegrasiAPI::create([
            'name' => "UMUM"
            'type' => 1,
            'base_url' => "https://api.example.com",
            'username' => "user_example",
            'password' => "password_example",
            'pin' => "123456",
        ]);
    }
}
