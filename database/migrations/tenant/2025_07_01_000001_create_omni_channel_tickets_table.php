<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOmniChannelTicketsTable extends Migration
{
    public function up()
    {
        Schema::create('omni_channel_tickets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('agent_id')->nullable();
            $table->string('status')->default('open');
            $table->string('priority')->default('medium');
            $table->string('category')->default('general');
            $table->text('description');
            $table->timestamp('due_date')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('agent_id')->references('id')->on('users')->onDelete('set null');
            
            // Add indexes for commonly queried fields
            $table->index(['status', 'priority']);
            $table->index('category');
            $table->index('due_date');
        });
    }

    public function down()
    {
        Schema::dropIfExists('omni_channel_tickets');
    }
}
