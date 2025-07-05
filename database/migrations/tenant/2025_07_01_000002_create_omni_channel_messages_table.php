<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('omni_channel_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->string('sender_type'); // 'user' atau 'agent'
            $table->unsignedBigInteger('sender_id');
            $table->text('message');
            $table->timestamps();

            $table->foreign('ticket_id')->references('id')->on('omni_channel_tickets')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('omni_channel_messages');
    }
};
