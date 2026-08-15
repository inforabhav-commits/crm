<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('webhook_inbox_entries', function (Blueprint $table) {
            $table->json('normalized_payload')->nullable()->after('payload');
            $table->string('normalized_event_type')->nullable()->after('normalized_payload')->index();
            $table->timestamp('normalized_at')->nullable()->after('processed_at');
        });
    }

    public function down()
    {
        Schema::table('webhook_inbox_entries', function (Blueprint $table) {
            $table->dropIndex(['normalized_event_type']);
            $table->dropColumn(['normalized_payload', 'normalized_event_type', 'normalized_at']);
        });
    }
};
