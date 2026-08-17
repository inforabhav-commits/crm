<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_logs', function (Blueprint $table) {
            $table->string('review_status')->nullable()->after('screen_pop_expires_at');
            $table->timestamp('reviewed_at')->nullable()->after('review_status');
            $table->foreignId('reconciled_by_id')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
            $table->text('reconciliation_notes')->nullable()->after('reconciled_by_id');
        });
    }

    public function down(): void
    {
        Schema::table('call_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reconciled_by_id');
            $table->dropColumn(['review_status', 'reviewed_at', 'reconciliation_notes']);
        });
    }
};
