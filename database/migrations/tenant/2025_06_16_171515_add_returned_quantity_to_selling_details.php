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
        Schema::table('selling_details', function (Blueprint $table) {
            $table->integer('returned_quantity')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('selling_details', function (Blueprint $table) {
            $table->dropColumn('returned_quantity');
        });
    }
};
