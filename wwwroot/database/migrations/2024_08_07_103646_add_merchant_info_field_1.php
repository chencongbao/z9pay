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
        Schema::table('merchant_infos', function (Blueprint $table) {
            $table->decimal('history_balance_amount',20,6)->default(0)->comment('历史余额');
            $table->dateTime('history_end_balance_amount_time')->nullable()->comment('余额历史截止时间');
            $table->dateTime('last_balance_amount_time')->nullable()->comment('最后变动时间');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('merchant_infos', function (Blueprint $table) {
            if (Schema::hasColumn('merchant_infos', 'history_balance_amount')) {
                $table->dropColumn('history_balance_amount');
            }
            if (Schema::hasColumn('merchant_infos', 'history_end_balance_amount_time')) {
                $table->dropColumn('history_end_balance_amount_time');
            }
            if (Schema::hasColumn('merchant_infos', 'last_balance_amount_time')) {
                $table->dropColumn('last_balance_amount_time');
            }
        });
    }
};
