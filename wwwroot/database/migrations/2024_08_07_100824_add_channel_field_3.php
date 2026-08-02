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
        Schema::table('channels', function (Blueprint $table) {
            $table->decimal('balance_amount',20,6)->default(0)->comment('账户余额');
            $table->dateTime('balance_update_time')->nullable()->comment('余额更新时间');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {

        Schema::table('channels', function (Blueprint $table) {
            if (Schema::hasColumn('channels', 'balance_amount')) {
                $table->dropColumn('balance_amount');
            }
            if (Schema::hasColumn('channels', 'balance_update_time')) {
                $table->dropColumn('balance_update_time');
            }
        });
    }
};
