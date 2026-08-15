<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('just_call_user_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('justcall_user_id');
            $table->unsignedBigInteger('active_user_id')->nullable();
            $table->string('active_justcall_user_id')->nullable();
            $table->string('justcall_name')->nullable();
            $table->string('justcall_email')->nullable();
            $table->string('justcall_phone')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('active_user_id');
            $table->unique('active_justcall_user_id');
            $table->index(['justcall_email', 'is_active']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('just_call_user_mappings');
    }
};
