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
        Schema::table('user_banks', function (Blueprint $table) {
            $table->integer('same_amount_interval_time')->default(0)->comment('待付款相同金额订单限制');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_banks', function (Blueprint $table) {
            if (Schema::hasColumn('user_banks', 'same_amount_interval_time')) {
                $table->dropColumn('same_amount_interval_time');
            }
        });
    }
};
