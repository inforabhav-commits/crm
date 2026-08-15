<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('qualification_status', 30)->nullable()->after('notes')->index();
            $table->text('qualification_need')->nullable()->after('qualification_status');
            $table->decimal('qualification_budget', 12, 2)->nullable()->after('qualification_need');
            $table->string('qualification_authority')->nullable()->after('qualification_budget');
            $table->string('qualification_timeline')->nullable()->after('qualification_authority');
            $table->string('qualification_interest_level', 30)->nullable()->after('qualification_timeline');
            $table->text('qualification_notes')->nullable()->after('qualification_interest_level');
            $table->dateTime('qualified_at')->nullable()->after('qualification_notes')->index();
            $table->foreignId('qualified_by_id')->nullable()->after('qualified_at')->constrained('users')->nullOnDelete();
            $table->dateTime('converted_at')->nullable()->after('qualified_by_id')->index();
            $table->foreignId('converted_by_id')->nullable()->after('converted_at')->constrained('users')->nullOnDelete();
            $table->foreignId('converted_customer_id')->nullable()->after('converted_by_id')->constrained('customers')->nullOnDelete();
            $table->foreignId('converted_contact_id')->nullable()->after('converted_customer_id')->constrained('contacts')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('converted_contact_id');
            $table->dropConstrainedForeignId('converted_customer_id');
            $table->dropConstrainedForeignId('converted_by_id');
            $table->dropColumn('converted_at');
            $table->dropConstrainedForeignId('qualified_by_id');
            $table->dropColumn([
                'qualified_at',
                'qualification_notes',
                'qualification_interest_level',
                'qualification_timeline',
                'qualification_authority',
                'qualification_budget',
                'qualification_need',
                'qualification_status',
            ]);
        });
    }
};
