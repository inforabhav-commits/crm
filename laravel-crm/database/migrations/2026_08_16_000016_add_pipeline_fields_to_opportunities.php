<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->string('currency', 3)->default('USD')->after('amount');
            $table->unsignedTinyInteger('probability')->default(10)->after('currency');
            $table->string('status', 30)->default('open')->after('probability')->index();
            $table->text('next_step')->nullable()->after('expected_close_date');
            $table->text('description')->nullable()->after('next_step');
            $table->foreignId('loss_reason_id')->nullable()->after('description')->constrained('crm_master_values')->nullOnDelete();
            $table->dateTime('closed_at')->nullable()->after('loss_reason_id')->index();
            $table->dateTime('won_at')->nullable()->after('closed_at');
            $table->dateTime('lost_at')->nullable()->after('won_at');
        });
    }

    public function down()
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropColumn(['lost_at', 'won_at', 'closed_at']);
            $table->dropConstrainedForeignId('loss_reason_id');
            $table->dropColumn(['description', 'next_step', 'status', 'probability', 'currency']);
        });
    }
};
