<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Tenant;
use App\Models\Domain;

class CreateTestTenant extends Command
{
    protected $signature = 'make:test-tenant';
    protected $description = 'Create a test tenant for webhook testing';

    public function handle()
    {
        // Create test tenant
        $tenant = Tenant::updateOrCreate(
            ['id' => 'test'],
            ['data' => ['name' => 'Test Tenant']]
        );

        // Create domain for tenant
        $domain = Domain::updateOrCreate(
            ['domain' => 'localhost'],
            ['tenant_id' => 'test']
        );

        $this->info('Test tenant created successfully!');
        $this->info('Tenant ID: test');
        $this->info('Domain: localhost');
    }
} 