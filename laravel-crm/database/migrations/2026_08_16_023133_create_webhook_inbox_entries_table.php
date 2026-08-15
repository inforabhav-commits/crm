<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('webhook_inbox_entries', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50)->index();
            $table->string('event_type')->nullable()->index();
            $table->string('external_id')->nullable();
            $table->string('payload_hash', 64);
            $table->json('payload');
            $table->timestamp('received_at')->index();
            $table->string('processing_status', 30)->default('pending')->index();
            $table->timestamp('processed_at')->nullable();
            $table->string('failure_summary')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamps();

            $table->unique(['provider', 'payload_hash']);
            $table->index(['provider', 'external_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('webhook_inbox_entries');
    }
};
