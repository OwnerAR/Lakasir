<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('integrasi_api', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Nama Integrasi API');
            $table->string('base_url')->comment('Base URL untuk API');
            $table->string('username')->comment('Username untuk API');
            $table->string('password')->comment('Password untuk API');
            $table->string('pin')->comment('PIN untuk API');
            $table->integer('type')->unique()->comment('Tipe Integrasi API, misalnya: "1", "2", dll.');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integrasi_api');
    }
};
