<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('company')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable()->index();
            $table->foreignId('lead_status_id')->constrained('crm_master_values');
            $table->foreignId('lead_source_id')->nullable()->constrained('crm_master_values')->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('priority', 30)->default('normal')->index();
            $table->dateTime('next_follow_up_at')->nullable()->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['lead_status_id', 'owner_id']);
            $table->index(['lead_source_id', 'owner_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('leads');
    }
};
