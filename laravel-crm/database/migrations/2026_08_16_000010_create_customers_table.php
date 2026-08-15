<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('company')->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->string('phone', 50)->nullable()->index();
            $table->string('website')->nullable();
            $table->string('industry')->nullable()->index();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('converted_from_lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['owner_id', 'is_active']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('customers');
    }
};
