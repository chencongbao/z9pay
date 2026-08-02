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
        Schema::create('merchant_balance_logs', function (Blueprint $table) {
            $table->id();
            $table->integer('mid')->index()->default(0)->comment('商户ID');
            $table->decimal("amount",20,2)->default(0)->comment("金额");
            $table->decimal("fee",20,2)->default(0)->comment("手续费");
            $table->tinyInteger('type')->default(0)->comment('类型');
            $table->tinyInteger('currency_id')->default(0)->comment('货币类型');
            $table->integer('type_id')->index()->comment("类型ID");
            $table->string("remark")->nullable()->comment("备注");
            $table->decimal("balance_amount",20,2)->default(0)->comment("账户金额");
            $table->decimal("settlement_amount",20,2)->default(0)->comment("结算中金额");
            $table->decimal("rate",10,3)->default(0)->comment("汇率");
            $table->tinyInteger('order_type')->default(0)->comment('1=充值，2=代付');
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
        Schema::dropIfExists('merchant_balance_logs');
    }
};
