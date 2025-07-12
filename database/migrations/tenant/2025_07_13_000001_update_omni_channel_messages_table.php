<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateOmniChannelMessagesTable extends Migration
{
    public function up()
    {
        Schema::table('omni_channel_messages', function (Blueprint $table) {
            // Drop existing columns that we don't need
            $table->dropForeign(['ticket_id']);
            $table->dropColumn(['ticket_id', 'sender_type', 'sender_id']);
            
            // Add new columns for WhatsApp integration
            $table->string('whatsapp_number');
            $table->string('customer_name')->nullable();
            $table->string('message_type')->default('text');
            $table->string('media_url')->nullable();
            $table->string('direction')->default('inbound');
            $table->string('status')->default('queued');
            $table->unsignedBigInteger('assigned_to')->nullable();
            
            // Add foreign key for assigned agent
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('omni_channel_messages', function (Blueprint $table) {
            // Remove new columns
            $table->dropForeign(['assigned_to']);
            $table->dropColumn([
                'whatsapp_number',
                'customer_name',
                'message_type',
                'media_url',
                'direction',
                'status',
                'assigned_to'
            ]);
            
            // Restore original columns
            $table->unsignedBigInteger('ticket_id');
            $table->string('sender_type');
            $table->unsignedBigInteger('sender_id');
            $table->foreign('ticket_id')->references('id')->on('omni_channel_tickets')->onDelete('cascade');
        });
    }
} 