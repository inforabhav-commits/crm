<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->index(['created_at', 'owner_id'], 'leads_created_owner_idx');
        });

        Schema::table('opportunities', function (Blueprint $table) {
            $table->index(['created_at', 'owner_id'], 'opp_created_owner_idx');
        });

        Schema::table('call_logs', function (Blueprint $table) {
            $table->index(['user_id', 'last_event_at'], 'calls_user_last_event_idx');
            $table->index(['provider', 'last_event_at'], 'calls_provider_last_event_idx');
        });

        Schema::table('webhook_inbox_entries', function (Blueprint $table) {
            $table->index(['provider', 'processing_status', 'received_at'], 'wh_provider_status_received_idx');
        });
    }

    public function down(): void
    {
        Schema::table('webhook_inbox_entries', function (Blueprint $table) {
            $table->dropIndex('wh_provider_status_received_idx');
        });
        Schema::table('call_logs', function (Blueprint $table) {
            $table->index('user_id', 'calls_user_fk_idx');
            $table->dropIndex('calls_user_last_event_idx');
            $table->dropIndex('calls_provider_last_event_idx');
        });
        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropIndex('opp_created_owner_idx');
        });
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('leads_created_owner_idx');
        });
    }
};
