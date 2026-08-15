<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_type_id')->constrained('crm_master_values');
            $table->string('subject');
            $table->text('description')->nullable();
            $table->nullableMorphs('related');
            $table->foreignId('assigned_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('priority', 30)->default('normal')->index();
            $table->string('status', 30)->default('pending')->index();
            $table->dateTime('due_at')->index();
            $table->dateTime('reminder_at')->nullable()->index();
            $table->dateTime('completed_at')->nullable()->index();
            $table->text('outcome')->nullable();
            $table->text('completion_notes')->nullable();
            $table->text('next_action')->nullable();
            $table->timestamps();

            $table->index(['assigned_user_id', 'status', 'due_at']);
            $table->index(['activity_type_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('activities');
    }
};
