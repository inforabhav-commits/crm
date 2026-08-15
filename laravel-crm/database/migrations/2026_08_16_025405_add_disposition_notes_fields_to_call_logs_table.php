<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('call_logs', function (Blueprint $table) {
            $table->string('provider_disposition')->nullable()->after('disposition');
            $table->string('crm_disposition')->nullable()->after('provider_disposition');
            $table->text('provider_notes')->nullable()->after('notes');
            $table->text('crm_notes')->nullable()->after('provider_notes');
            $table->boolean('follow_up_required')->default(false)->after('crm_notes');
            $table->foreignId('follow_up_activity_id')->nullable()->after('follow_up_required')->constrained('activities')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('call_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('follow_up_activity_id');
            $table->dropColumn([
                'provider_disposition',
                'crm_disposition',
                'provider_notes',
                'crm_notes',
                'follow_up_required',
            ]);
        });
    }
};
