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
        Schema::table('users', function (Blueprint $table) {
            $table->integer('limit_deposit_paid_number')->default(0)->comment('待付款相同金额订单限制');
            $table->tinyInteger('auto_refresh')->default(0)->comment('自动刷新');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'limit_deposit_paid_number')) {
                $table->dropColumn('limit_deposit_paid_number');
            }
            if (Schema::hasColumn('users', 'auto_refresh')) {
                $table->dropColumn('auto_refresh');
            }
        });
    }
};
