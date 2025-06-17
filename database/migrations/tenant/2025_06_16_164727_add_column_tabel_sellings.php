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
        Schema::table('sellings', function (Blueprint $table) {
            $table->boolean('is_fully_returned')->default(false);
            $table->decimal('returned_amount', 15, 2)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sellings', function (Blueprint $table) {
            $table->dropColumn('is_fully_returned');
            $table->dropColumn('returned_amount');
        });
    }
};
