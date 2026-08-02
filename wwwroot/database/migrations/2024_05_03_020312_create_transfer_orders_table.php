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
        Schema::create('transfer_orders', function (Blueprint $table) {
            $table->id();
            $table->integer('mid')->default(0)->index()->comment('商户ID');
            $table->decimal('amount',10,2)->default(0)->comment('订单代付金额');
            $table->decimal('actual_amount',10,2)->default(0)->comment('实际代付订单金额');

            $table->string('time',20)->nullable()->comment('提交时间戳');
            $table->tinyInteger('currency_id')->default(0)->comment('货币类型 CNY、USDT、 VND、INR');
            $table->string('order_no',100)->nullable()->comment('商户唯一订单号');

            $table->string('ip',50)->nullable()->comment('客户IP地址');
            $table->string('true_ip',50)->nullable()->comment('真实客户IP地址');
            $table->string('ordernumber',100)->nullable()->unique()->comment('订单号');
            $table->string('uid',100)->default(0)->index()->comment('商户提供的会员ID');
            $table->string('level',100)->nullable()->comment('商户提供的会员等级');

            $table->string('notify_url')->nullable()->comment('商户提供通知付款结果的接口地址');
            $table->string('withdrawQueryUrl')->nullable()->comment('商户提供的反查接口地址');
            $table->string('callToken')->nullable()->comment('商户提供的反查接口调用凭证');

            $table->integer('callback_count')->default(0)->comment('回调次数');
            $table->integer("callback_time")->default(0)->comment('回调时间');
            $table->integer("success_time")->default(0)->comment("成功时间");

            $table->string('remark',255)->nullable()->comment('备注');
            $table->string("extra")->nullable()->comment('穿透参数，原样返回商户的参数');


            $table->integer('bank_id')->default(0)->index()->comment('银行ID');
            $table->string('bank_code',100)->nullable()->comment('银行代码');
            $table->string('bank_name',100)->nullable()->comment('银行名称');
            $table->string('card_no',100)->nullable()->comment('银行卡号');
            $table->string('holder_name',100)->nullable()->comment('银行卡户名，或者 支付宝/微信 真实姓名');
            $table->string('bank_province',100)->nullable()->comment('开户行省份');
            $table->string('bank_city',100)->nullable()->comment('开户行城市');
            $table->string('bank_branch',100)->nullable()->comment('银行分行地址');
            $table->string('bank_mobile',100)->nullable()->comment('开户行手机号');


            $table->tinyInteger('status')->default(0)->index()->comment('订单状态');
            $table->tinyInteger('hand_success')->default(0)->comment('手动成功，1=手动成功，0=自动成功');
            $table->integer('hand_admin_id')->default(0)->index()->comment("手工补单人");
            $table->integer('pid')->default(0)->index()->comment('父订单');

            $table->decimal("merchant_rate",10,2)->default(0)->comment('商户支付费率');
            $table->decimal("merchant_fee",10,2)->default(0)->comment('商户手续费');
            $table->decimal("merchant_agent1_rate",10,2)->default(0)->comment('商户一级代理费率');
            $table->decimal("merchant_agent1_commission",10,2)->default(0)->comment('商户一级代理佣金');
            $table->decimal("merchant_agent2_rate",10,2)->default(0)->comment('商户二级代理费率');
            $table->decimal("merchant_agent2_commission",10,2)->default(0)->comment('商户二级代理佣金');
            $table->integer("merchant_agent1_id")->default(0)->comment("一级代理ID");
            $table->integer("merchant_agent2_id")->default(0)->comment("二级代理ID");
            $table->integer("merchant_action_id")->default(0)->comment("商户操作用户");

            $table->integer('user_id')->default(0)->index()->comment('金主ID');
            $table->decimal("user_rate",10,2)->default(0)->comment('金主费率');
            $table->decimal("user_commission",10,2)->default(0)->comment('金主佣金');
            $table->decimal("user_agent1_rate",10,2)->default(0)->comment('金主一级代理费率');
            $table->decimal("user_agent1_commission",10,2)->default(0)->comment('金主一级代理佣金');
            $table->decimal("user_agent2_rate",10,2)->default(0)->comment('金主二级代理费率');
            $table->decimal("user_agent2_commission",10,2)->default(0)->comment('金主二级代理佣金');
            $table->integer("user_agent1_id")->default(0)->comment("一级代理ID");
            $table->integer("user_agent2_id")->default(0)->comment("二级代理ID");
            $table->string('pay_certificate_1')->nullable()->comment('带公章的回执单');
            $table->string('pay_certificate_2')->nullable()->comment('带完整卡号的回执单');
            $table->string('pay_certificate_3')->nullable()->comment('带银行流水的回执单');

            $table->tinyInteger('type')->default(0)->comment('0=代付，1=结算');
            $table->integer('channel_id')->default(0)->index()->comment('渠道ID');
            $table->integer('channel_account_id')->default(0)->index()->comment('渠道账号ID');
            $table->string('channel_ordernumber',100)->nullable()->comment('渠道单号');


            $table->integer('resetpay_number')->default(0)->index()->comment('重付次数');
            $table->integer('child_count')->default(0)->index()->comment('子订单数量');

            $table->tinyInteger('hour')->default(0)->index()->comment('小时');

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
        Schema::dropIfExists('transfer_orders');
    }
};
