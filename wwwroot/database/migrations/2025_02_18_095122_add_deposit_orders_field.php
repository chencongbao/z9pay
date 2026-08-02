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
            $table->integer("merchant_agent3_id")->default(0)->comment("三级代理ID");
            $table->decimal("merchant_agent3_rate",10,2)->default(0)->comment('商户三级代理费率');
            $table->decimal("merchant_agent3_commission",10,2)->default(0)->comment('商户三级代理佣金');
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
            if (Schema::hasColumn('deposit_orders', 'merchant_agent3_id')) {
                $table->dropColumn('merchant_agent3_id');
            }
            if (Schema::hasColumn('deposit_orders', 'merchant_agent3_rate')) {
                $table->dropColumn('merchant_agent3_rate');
            }
            if (Schema::hasColumn('deposit_orders', 'merchant_agent3_commission')) {
                $table->dropColumn('merchant_agent3_commission');
            }
        });
    }
};
