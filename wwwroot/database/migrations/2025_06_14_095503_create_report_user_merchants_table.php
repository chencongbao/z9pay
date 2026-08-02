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
        Schema::create('report_user_merchants', function (Blueprint $table) {
            $table->id();
            $table->date("date_add")->nullable()->index()->comment("统计日期");
            $table->integer("uid")->default(0)->index()->comment("金主ID");
            $table->integer("mid")->default(0)->index()->comment("商户ID");

            $table->integer("deposit_order_number_total")->default(0)->comment("总单数");
            $table->integer("deposit_order_number_success")->default(0)->comment("成功单数");
            $table->integer("deposit_order_number_fail")->default(0)->comment("失败单数");
            $table->integer("deposit_order_number_overtime")->default(0)->comment("超时单数");
            $table->integer("deposit_order_number_swiping")->default(0)->comment("刷单数");
            $table->decimal("deposit_order_total_amount",30,2)->default(0)->comment("代收总跑量，成功单子总代收");
            $table->decimal("deposit_order_total_fee",30,2)->default(0)->comment("代收总手续费，成功单子总代收");

            $table->integer("transfer_order_number_total")->default(0)->comment("总单数");
            $table->integer("transfer_order_number_success")->default(0)->comment("成功单数");
            $table->integer("transfer_order_number_fail")->default(0)->comment("失败单数");
            $table->decimal("transfer_order_total_amount",30,2)->default(0)->comment("代付总跑量，成功单子总代收");
            $table->decimal("transfer_order_total_fee",30,2)->default(0)->comment("代付总手续费，成功单子总代收");

            $table->integer("settlement_order_number_total")->default(0)->comment("总单数");
            $table->integer("settlement_order_number_success")->default(0)->comment("成功单数");
            $table->integer("settlement_order_number_fail")->default(0)->comment("失败单数");
            $table->decimal("settlement_order_total_amount",30,2)->default(0)->comment("代付总跑量，成功单子总代收");
            $table->decimal("settlement_order_total_fee",30,2)->default(0)->comment("代付总手续费，成功单子总代收");

            $table->decimal("deposit_commission",30,2)->default(0)->comment("代收佣金");
            $table->decimal("transfer_commission",30,2)->default(0)->comment("代付佣金");
            $table->decimal("settlement_commission",30,2)->default(0)->comment("结算佣金");

            $table->decimal("deposit_one_agent_commission",30,2)->default(0)->comment("一级代理佣金");
            $table->decimal("deposit_two_agent_commission",30,2)->default(0)->comment("二级代理佣金");
            $table->decimal("deposit_three_agent_commission",30,2)->default(0)->comment("三级代理佣金");
            $table->decimal("deposit_four_agent_commission",30,2)->default(0)->comment("四级代理佣金");
            $table->decimal("deposit_five_agent_commission",30,2)->default(0)->comment("五级代理佣金");

            $table->decimal("transfer_one_agent_commission",30,2)->default(0)->comment("一级代理佣金");
            $table->decimal("transfer_two_agent_commission",30,2)->default(0)->comment("二级代理佣金");
            $table->decimal("transfer_three_agent_commission",30,2)->default(0)->comment("三级代理佣金");
            $table->decimal("transfer_four_agent_commission",30,2)->default(0)->comment("四级代理佣金");
            $table->decimal("transfer_five_agent_commission",30,2)->default(0)->comment("五级代理佣金");

            $table->decimal("settlement_one_agent_commission",30,2)->default(0)->comment("一级代理佣金");
            $table->decimal("settlement_two_agent_commission",30,2)->default(0)->comment("二级代理佣金");
            $table->decimal("settlement_three_agent_commission",30,2)->default(0)->comment("三级代理佣金");
            $table->decimal("settlement_four_agent_commission",30,2)->default(0)->comment("四级代理佣金");
            $table->decimal("settlement_five_agent_commission",30,2)->default(0)->comment("五级代理佣金");

            $table->decimal("commission_jian_total_amount",30,2)->default(0)->comment("佣金减项资金");
            $table->decimal("commission_add_total_amount",30,2)->default(0)->comment("佣金增项资金");

            $table->decimal("deposit_jian_total_amount",30,2)->default(0)->comment("代收减项资金");
            $table->decimal("deposit_add_total_amount",30,2)->default(0)->comment("代收增项资金");

            $table->decimal("transfer_jian_total_amount",30,2)->default(0)->comment("代付减项资金");
            $table->decimal("transfer_add_total_amount",30,2)->default(0)->comment("代付增项资金");
            $table->decimal("deposit_profit",30,2)->default(0)->comment("代收利润");
            $table->decimal("transfer_profit",30,2)->default(0)->comment("代付利润");
            $table->decimal("settlement_profit",30,2)->default(0)->comment("结算利润");



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
        Schema::dropIfExists('report_user_merchants');
    }
};
