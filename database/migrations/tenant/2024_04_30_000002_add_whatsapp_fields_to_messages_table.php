<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('omni_channel_messages', function (Blueprint $table) {
            $table->string('whatsapp_number')->nullable()->after('queue_position');
            $table->string('customer_name')->nullable()->after('whatsapp_number');
            $table->string('direction')->default('inbound')->after('customer_name');
            $table->string('message_type')->default('text')->after('direction');
            $table->text('media_url')->nullable()->after('message_type');
        });
    }

    public function down()
    {
        Schema::table('omni_channel_messages', function (Blueprint $table) {
            $table->dropColumn([
                'whatsapp_number',
                'customer_name',
                'direction',
                'message_type',
                'media_url'
            ]);
        });
    }
}; 