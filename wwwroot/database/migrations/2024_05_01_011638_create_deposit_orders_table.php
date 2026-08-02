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
        Schema::create('deposit_orders', function (Blueprint $table) {
            $table->id();
            $table->integer('mid')->default(0)->index()->comment('商户ID');
            $table->decimal('amount',10,2)->default(0)->comment('订单充值金额');

            $table->string('time',20)->nullable()->comment('提交时间戳');
            $table->tinyInteger('currency_id')->default(0)->comment('货币类型 CNY、USDT、 VND、INR');
            $table->tinyInteger('payment_id')->default(0)->index()->comment('gateway');
            $table->string('order_no',100)->nullable()->comment('商户唯一订单号');
            $table->decimal('pay_amount',10,2)->default(0)->comment('实际支付订单金额');
            $table->decimal('actual_amount',10,2)->default(0)->comment('实际支付订单金额');
            $table->decimal('freeze_amount',10,2)->default(0)->comment('冻结金额');
            $table->string('ip',50)->nullable()->comment('客户IP地址');
            $table->string('true_ip',50)->nullable()->comment('真实客户IP地址');
            $table->string('ordernumber',100)->nullable()->unique()->comment('订单号');
            $table->string('uid',100)->default(0)->index()->comment('商户提供的会员ID');
            $table->string('level',100)->nullable()->comment('商户提供的会员等级');
            $table->string('notify_url')->nullable()->comment('商户提供通知付款结果的接口地址');
            $table->string('return_url')->nullable()->comment('同步跳转商户平台的地址');
            $table->string("extra")->nullable()->comment('穿透参数，原样返回商户的参数');
            $table->string("tag")->nullable()->comment('登录商户后台输入的商户代码');
            $table->string('bank',100)->nullable()->comment('会员付款银行代码，或者 支付宝=ALIPAY，微信 =WECHAT');
            $table->string('name',100)->nullable()->comment('会员姓名');
            $table->string('email',100)->nullable()->comment('会员邮箱');
            $table->string('phone',100)->nullable()->comment('会员电话');
            $table->text('data_type')->nullable()->comment('如需返回收款卡信息，商户自己封装收银台，请传json');
            $table->string('fee',255)->nullable()->comment('充值订单产生的手续费');

            $table->string('pay_name',50)->nullable()->comment('付款人姓名');
            $table->tinyInteger('order_type')->default(1)->comment('订单类型，1商户提单，2=手工补单');
            $table->tinyInteger('pay_status')->default(0)->comment('支付状态，待支付，付方已确认，付方已取消');
            $table->tinyInteger('status')->default(1)->comment('订单状态,成功/失败/超时/创建/待支付/刷单');
            $table->integer('user_id')->default(0)->index()->comment('金主ID');
            $table->integer('channel_id')->default(0)->index()->comment('渠道ID');
            $table->integer('channel_account_id')->default(0)->index()->comment('渠道账号ID');
            $table->integer('user_bank_id')->default(0)->index()->comment('银行卡ID');
            $table->integer('callback_count')->default(0)->comment('回调次数');
            $table->integer("callback_time")->default(0)->comment('回调时间');
            $table->integer("success_time")->default(0)->comment("成功时间");
            $table->string('channel_ordernumber',100)->nullable()->comment('渠道单号');
            $table->string('channel_pay_url',255)->nullable()->comment('渠道支付url');

            $table->tinyInteger('hand_success')->default(0)->comment('手动成功，1=手动成功，0=自动成功');
            $table->integer('hand_admin_id')->default(0)->index()->comment("手工补单人");

            $table->string('bank_code',100)->nullable()->comment('银行代码');
            $table->string('bank_name',100)->nullable()->comment('银行名称');
            $table->string('card_no',100)->nullable()->comment('银行卡号');
            $table->string('card_name',100)->nullable()->comment('银行卡所属人');

            $table->string('remark',255)->nullable()->comment('备注');


            $table->integer("merchant_agent1_id")->default(0)->comment("一级代理ID");
            $table->integer("merchant_agent2_id")->default(0)->comment("二级代理ID");
            $table->decimal("merchant_rate",10,2)->default(0)->comment('商户支付费率');
            $table->decimal("merchant_fee",10,2)->default(0)->comment('商户手续费');
            $table->decimal("merchant_agent1_rate",10,2)->default(0)->comment('商户一级代理费率');
            $table->decimal("merchant_agent1_commission",10,2)->default(0)->comment('商户一级代理佣金');
            $table->decimal("merchant_agent2_rate",10,2)->default(0)->comment('商户二级代理费率');
            $table->decimal("merchant_agent2_commission",10,2)->default(0)->comment('商户二级代理佣金');


            $table->decimal("user_rate",10,2)->default(0)->comment('金主费率');
            $table->decimal("user_commission",10,2)->default(0)->comment('金主佣金');
            $table->decimal("user_agent1_rate",10,2)->default(0)->comment('金主一级代理费率');
            $table->decimal("user_agent1_commission",10,2)->default(0)->comment('金主一级代理佣金');
            $table->decimal("user_agent2_rate",10,2)->default(0)->comment('金主二级代理费率');
            $table->decimal("user_agent2_commission",10,2)->default(0)->comment('金主二级代理佣金');
            $table->integer("user_agent1_id")->default(0)->comment("一级代理ID");
            $table->integer("user_agent2_id")->default(0)->comment("二级代理ID");


            $table->integer("bank_id")->default(0)->comment("银行ID");
            $table->string('collection_name',100)->nullable()->comment('收款人姓名');
            $table->string('collection_card_no',100)->nullable()->comment('收款人账户');
            $table->string('collection_bank_name',100)->nullable()->comment('收款人银行名称');
            $table->string('collection_qrcode')->nullable()->comment('收款人二维码');
            $table->string('collection_bank_code',100)->nullable()->comment('收款人银行代码');
            $table->tinyInteger('hour')->default(0)->index()->comment('小时');

            $table->integer("expired_time")->default(0)->comment("订单付款过期时间");

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
        Schema::dropIfExists('deposit_orders');
    }
};
