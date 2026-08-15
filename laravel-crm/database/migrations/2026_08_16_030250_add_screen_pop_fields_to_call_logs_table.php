<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('call_logs', function (Blueprint $table) {
            $table->timestamp('screen_pop_dismissed_at')->nullable()->after('recording_fetched_at');
            $table->timestamp('screen_pop_expires_at')->nullable()->after('screen_pop_dismissed_at')->index();
        });
    }

    public function down()
    {
        Schema::table('call_logs', function (Blueprint $table) {
            $table->dropColumn(['screen_pop_dismissed_at', 'screen_pop_expires_at']);
        });
    }
};
