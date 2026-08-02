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
        Schema::create('user_trade_logs', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id')->index()->default(0)->comment('商户ID');
            $table->integer("user_agent1_id")->default(0)->comment("一级代理ID");
            $table->integer("user_agent2_id")->default(0)->comment("二级代理ID");
            $table->string('order_no',100)->nullable()->comment('商户唯一订单号');
            $table->string('ordernumber',100)->nullable()->comment('订单号');
            $table->tinyInteger('type')->default(0)->comment('类型');
            $table->decimal("amount",20,2)->default(0)->comment("金额");
            $table->decimal("commission",10,2)->default(0)->comment('金主佣金');
            $table->integer('action_admin_id')->default(0)->index()->comment("手工补单人");
            $table->decimal("balance_amount",20,2)->default(0)->comment("账户金额");
            $table->string('remark',255)->nullable()->comment('备注');
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
        Schema::dropIfExists('user_trade_logs');
    }
};
