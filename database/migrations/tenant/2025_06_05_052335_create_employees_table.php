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
        Schema::create('employees', function (Blueprint $table) {
            $table->id(); // Changed to employee_id for clarity
            $table->string('employeeId')->unique(); // Unique identifier for each employee
            $table->string('name');
            $table->string('whatsappId')->unique();
            $table->string('position')->nullable(); // e.g., manager, staff
            $table->decimal('salary', 10, 2)->default(0.00); // Monthly salary
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
