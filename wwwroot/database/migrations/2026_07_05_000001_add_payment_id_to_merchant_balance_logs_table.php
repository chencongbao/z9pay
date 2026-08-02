<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('merchant_balance_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('merchant_balance_logs', 'payment_id')) {
                $table->integer('payment_id')->default(0)->comment('支付ID')->after('currency_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('merchant_balance_logs', function (Blueprint $table) {
            if (Schema::hasColumn('merchant_balance_logs', 'payment_id')) {
                $table->dropColumn('payment_id');
            }
        });
    }
};
