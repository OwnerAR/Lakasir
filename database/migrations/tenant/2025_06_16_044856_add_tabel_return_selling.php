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
        Schema::create('return_sellings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('selling_id')->constrained('sellings');
            $table->string('return_number')->unique();
            $table->decimal('total_amount', 15, 2);
            $table->string('return_reason')->nullable();
            $table->enum('refund_method', ['cash', 'store_credit', 'replace_item']);
            $table->enum('status', ['pending', 'processed', 'completed', 'rejected'])->default('pending');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        // Migrasi untuk return_selling_details
        Schema::create('return_selling_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_selling_id')->constrained('return_sellings');
            $table->foreignId('selling_detail_id')->constrained('selling_details');
            $table->foreignId('product_id')->constrained('products');
            $table->integer('quantity');
            $table->decimal('price', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->string('reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
