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
            $table->tinyInteger('status')->default(1)->index()->comment("结算状态");
            $table->tinyInteger('settlement_mode')->default(0)->comment('0=T0结算，1=T1结算，2=T2结算');
            $table->integer("settlement_time")->default(0)->comment("结算时间");
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
            if (Schema::hasColumn('merchant_balance_logs', 'settlement_mode')) {
                $table->dropColumn('settlement_mode');
            }
            if (Schema::hasColumn('merchant_balance_logs', 'settlement_time')) {
                $table->dropColumn('settlement_time');
            }
            if (Schema::hasColumn('merchant_balance_logs', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
