<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('opportunity_stage_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained('opportunities')->cascadeOnDelete();
            $table->foreignId('from_stage_id')->nullable()->constrained('crm_master_values')->nullOnDelete();
            $table->foreignId('to_stage_id')->constrained('crm_master_values');
            $table->foreignId('changed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->dateTime('changed_at')->index();
            $table->timestamps();

            $table->index(['opportunity_id', 'changed_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('opportunity_stage_histories');
    }
};
