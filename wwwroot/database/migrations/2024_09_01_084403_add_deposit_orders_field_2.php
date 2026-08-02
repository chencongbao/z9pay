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
        Schema::table('deposit_orders', function (Blueprint $table) {
            $table->decimal('user_agent3_commission',10,2)->default(0)->comment('金主三级代理佣金');
            $table->decimal('user_agent3_rate',10,6)->default(0)->comment('金主三级代理费率');
            $table->integer('user_agent3_id')->default(0)->comment('金主三级代理ID');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('deposit_orders', function (Blueprint $table) {
            if (Schema::hasColumn('deposit_orders', 'user_agent3_commission')) {
                $table->dropColumn('user_agent3_commission');
            }
            if (Schema::hasColumn('deposit_orders', 'user_agent3_rate')) {
                $table->dropColumn('user_agent3_rate');
            }
            if (Schema::hasColumn('deposit_orders', 'user_agent3_id')) {
                $table->dropColumn('user_agent3_id');
            }
        });
    }
};
