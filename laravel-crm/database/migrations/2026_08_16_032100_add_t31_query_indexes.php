<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->index(['created_at', 'owner_id']);
        });

        Schema::table('opportunities', function (Blueprint $table) {
            $table->index(['created_at', 'owner_id']);
        });

        Schema::table('call_logs', function (Blueprint $table) {
            $table->index(['user_id', 'last_event_at']);
            $table->index(['provider', 'last_event_at']);
        });

        Schema::table('webhook_inbox_entries', function (Blueprint $table) {
            $table->index(['provider', 'processing_status', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::table('webhook_inbox_entries', function (Blueprint $table) {
            $table->dropIndex(['provider', 'processing_status', 'received_at']);
        });
        Schema::table('call_logs', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'last_event_at']);
            $table->dropIndex(['provider', 'last_event_at']);
        });
        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropIndex(['created_at', 'owner_id']);
        });
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['created_at', 'owner_id']);
        });
    }
};
