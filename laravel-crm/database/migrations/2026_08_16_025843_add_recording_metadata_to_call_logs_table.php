<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('call_logs', function (Blueprint $table) {
            $table->string('recording_status', 30)->default('unavailable')->after('recording_url')->index();
            $table->unsignedInteger('recording_duration_seconds')->nullable()->after('recording_status');
            $table->timestamp('recording_fetched_at')->nullable()->after('recording_duration_seconds');
        });
    }

    public function down()
    {
        Schema::table('call_logs', function (Blueprint $table) {
            $table->dropColumn([
                'recording_status',
                'recording_duration_seconds',
                'recording_fetched_at',
            ]);
        });
    }
};
