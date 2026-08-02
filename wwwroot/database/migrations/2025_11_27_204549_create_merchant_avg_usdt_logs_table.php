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
        Schema::create('merchant_avg_usdt_logs', function (Blueprint $table) {
            $table->id();
            $table->integer('mid')->index()->default(0)->comment('商户ID');
            $table->bigInteger("order_id")->index()->comment("所属订单");

            $table->decimal("account_cny",20,8)->default(0)->comment("商户余额");
            $table->decimal("account_usdt_rate",20,8)->default(0)->comment("商户平均USDT费率");
            $table->decimal("account_usdt",20,8)->default(0)->comment("商户USDT余额");


            $table->decimal("order_cny",20,8)->default(0)->comment("订单金额");
            $table->decimal("order_usdt_rate",20,8)->default(0)->comment("订单实时USDT费率");
            $table->decimal("order_usdt",20,8)->default(0)->comment("订单USDT金额");


            $table->decimal("total_cny",20,8)->default(0)->comment("总人名币金额");
            $table->decimal("total_usdt",20,8)->default(0)->comment("总人USDT金额");
            $table->decimal("usdt_avg_rate",20,8)->default(0)->comment("最终计算出的USDT平均费率");


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('merchant_avg_usdt_logs');
    }
};
