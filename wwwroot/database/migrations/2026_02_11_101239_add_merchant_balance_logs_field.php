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
            $table->string('ordernumber',100)->nullable()->index()->comment('订单号');
            $table->string('order_no',100)->nullable()->index()->comment('订单号');
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
            $table->dropColumn('ordernumber');
            $table->dropColumn('order_no');
        });
    }
};
