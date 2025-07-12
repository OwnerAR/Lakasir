<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('omni_channel_messages', function (Blueprint $table) {
            $table->string('status')->default('queued')->after('message');
            $table->unsignedBigInteger('assigned_to')->nullable()->after('status');
            $table->string('priority')->default('medium')->after('assigned_to');
            $table->integer('queue_position')->nullable()->after('priority');
            
            $table->foreign('assigned_to')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('omni_channel_messages', function (Blueprint $table) {
            $table->dropForeign(['assigned_to']);
            $table->dropColumn(['status', 'assigned_to', 'priority', 'queue_position']);
        });
    }
}; 