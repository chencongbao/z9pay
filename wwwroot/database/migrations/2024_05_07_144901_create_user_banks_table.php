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
        Schema::create('user_banks', function (Blueprint $table) {
            $table->id();
            $table->integer('bank_id')->default(0)->index();
            $table->string('card_no',100)->nullable()->comment('银行卡号,支付宝账户');
            $table->string("name",50)->nullable()->comment('银行账户名，支付宝收款姓名');
            $table->string("bank_branch",255)->nullable()->comment("支行");
            $table->string("payment_qrcode",255)->nullable()->comment("收款码");
            $table->integer("user_id")->default(0)->index()->comment('所属金主');
            $table->decimal("balance_amount",10,2)->default(0)->comment("参考余额");
            $table->decimal("limint_day_amount",10,2)->default(0)->comment("全天限额");
            $table->decimal("limint_min_amount",10,2)->default(0)->comment("单笔最小限额");
            $table->decimal("limint_max_amount",10,2)->default(0)->comment("单笔最大限额");
            $table->tinyInteger('status')->default(0)->comment('状态');
            $table->tinyInteger('doing_status')->default(0)->comment('是否在支付中');
            $table->integer('limit_day_order_number')->default(0)->comment('该号一整天收款的额度上限制');
            $table->string('merchant_user_ids',255)->nullable()->comment('分组标示');
            $table->string('remark',255)->nullable()->comment('备注');
            $table->tinyInteger('collection_status')->default(0)->comment('收单状态');
            $table->tinyInteger('is_mobile_bank')->default(0)->comment('手机银行');
            $table->integer('payment_id')->default(0)->comment('收款方式');
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
        Schema::dropIfExists('user_banks');
    }
};
