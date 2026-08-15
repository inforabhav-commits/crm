<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('call_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50)->index();
            $table->string('external_call_id')->nullable();
            $table->string('external_event_id')->nullable()->index();
            $table->string('direction', 20)->default('unknown')->index();
            $table->string('status', 50)->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('agent_external_id')->nullable()->index();
            $table->string('from_number')->nullable();
            $table->string('to_number')->nullable();
            $table->string('customer_number')->nullable();
            $table->string('customer_number_normalized')->nullable()->index();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('disposition')->nullable();
            $table->text('notes')->nullable();
            $table->string('recording_reference')->nullable();
            $table->text('recording_url')->nullable();
            $table->timestamp('last_event_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['provider', 'external_call_id']);
            $table->index(['provider', 'external_event_id']);
            $table->index(['provider', 'direction', 'status']);
        });

        Schema::table('webhook_inbox_entries', function (Blueprint $table) {
            $table->foreignId('call_log_id')->nullable()->after('normalized_at')->constrained('call_logs')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('webhook_inbox_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('call_log_id');
        });

        Schema::dropIfExists('call_logs');
    }
};
