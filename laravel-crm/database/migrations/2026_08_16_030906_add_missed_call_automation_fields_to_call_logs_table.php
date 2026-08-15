<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_logs', function (Blueprint $table) {
            $table->string('missed_call_automation_status', 40)->default('not_applicable')->after('screen_pop_expires_at')->index();
            $table->foreignId('missed_call_activity_id')->nullable()->after('missed_call_automation_status')->constrained('activities')->nullOnDelete();
            $table->timestamp('missed_call_automated_at')->nullable()->after('missed_call_activity_id');
            $table->string('missed_call_automation_reason')->nullable()->after('missed_call_automated_at');
        });
    }

    public function down(): void
    {
        Schema::table('call_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('missed_call_activity_id');
            $table->dropColumn([
                'missed_call_automation_status',
                'missed_call_automated_at',
                'missed_call_automation_reason',
            ]);
        });
    }
};
