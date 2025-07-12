<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenants\User;
use Illuminate\Support\Str;

class CreateTestApiUser extends Command
{
    protected $signature = 'make:test-api-user';
    protected $description = 'Create a test user with API key for webhook testing';

    public function handle()
    {
        $apiKey = 'test-api-key-123';
        
        // Create or update test user
        $user = User::updateOrCreate(
            ['email' => 'test-webhook@example.com'],
            [
                'name' => 'Webhook Test User',
                'password' => bcrypt('password'),
                'api_key' => $apiKey,
            ]
        );

        $this->info('Test API user created successfully!');
        $this->info('API Key: ' . $apiKey);
        $this->info('Email: test-webhook@example.com');
    }
} 