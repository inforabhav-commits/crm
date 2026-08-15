<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('crm_master_values', function (Blueprint $table) {
            $table->id();
            $table->string('type', 80)->index();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('color', 20)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['type', 'slug']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('crm_master_values');
    }
};
