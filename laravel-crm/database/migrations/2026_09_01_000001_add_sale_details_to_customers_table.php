<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('external_customer_id')->nullable()->after('id')->index();
            $table->string('sale_date')->nullable()->after('address');
            $table->string('amount')->nullable()->after('sale_date');
            $table->string('plan')->nullable()->after('amount');
            $table->string('software')->nullable()->after('plan');
            $table->string('license_number')->nullable()->after('software');
            $table->string('product_number')->nullable()->after('license_number');
            $table->text('file_password')->nullable()->after('product_number');
            $table->string('cloud_customer')->nullable()->after('file_password');
            $table->string('customer_user_id')->nullable()->after('cloud_customer');
            $table->text('customer_password')->nullable()->after('customer_user_id');
            $table->text('issue')->nullable()->after('customer_password');
            $table->string('sale_type')->nullable()->after('issue');
            $table->string('no_of_cases')->nullable()->after('sale_type');
            $table->string('payment_type')->nullable()->after('no_of_cases');
            $table->string('last_4', 4)->nullable()->after('payment_type');
            $table->string('card_type')->nullable()->after('last_4');
            $table->string('end')->nullable()->after('card_type');
        });
    }

    public function down()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'external_customer_id',
                'sale_date',
                'amount',
                'plan',
                'software',
                'license_number',
                'product_number',
                'file_password',
                'cloud_customer',
                'customer_user_id',
                'customer_password',
                'issue',
                'sale_type',
                'no_of_cases',
                'payment_type',
                'last_4',
                'card_type',
                'end',
            ]);
        });
    }
};
