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
        Schema::table('transfer_orders', function (Blueprint $table) {
            $table->decimal('user_agent4_commission',10,2)->default(0)->comment('金主4级代理佣金');
            $table->decimal('user_agent4_rate',10,6)->default(0)->comment('金主4级代理费率');
            $table->integer('user_agent4_id')->default(0)->comment('金主4级代理ID');
            $table->decimal('user_agent5_commission',10,2)->default(0)->comment('金主5级代理佣金');
            $table->decimal('user_agent5_rate',10,6)->default(0)->comment('金主5级代理费率');
            $table->integer('user_agent5_id')->default(0)->comment('金主5级代理ID');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transfer_orders', function (Blueprint $table) {
            if (Schema::hasColumn('transfer_orders', 'user_agent4_commission')) {
                $table->dropColumn('user_agent4_commission');
            }
            if (Schema::hasColumn('transfer_orders', 'user_agent4_rate')) {
                $table->dropColumn('user_agent4_rate');
            }
            if (Schema::hasColumn('transfer_orders', 'user_agent4_id')) {
                $table->dropColumn('user_agent4_id');
            }

            if (Schema::hasColumn('transfer_orders', 'user_agent5_commission')) {
                $table->dropColumn('user_agent5_commission');
            }
            if (Schema::hasColumn('transfer_orders', 'user_agent5_rate')) {
                $table->dropColumn('user_agent5_rate');
            }
            if (Schema::hasColumn('transfer_orders', 'user_agent5_id')) {
                $table->dropColumn('user_agent5_id');
            }
        });
    }
};
