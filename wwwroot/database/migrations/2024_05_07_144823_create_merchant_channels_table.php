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
        Schema::create('merchant_channels', function (Blueprint $table) {
            $table->id();
            $table->integer('merchant_user_id')->default(0)->comment('商户ID');
            $table->integer('channel_id')->default(0)->comment('渠道ID');
            $table->integer('payment_id')->default(0)->comment('支付ID');
            $table->integer('priority')->default(0)->comment('优先级');
            $table->tinyInteger('status')->default(0)->comment('状态');
            $table->decimal('pay_min_amount',10,2)->default(0)->comment('充值单笔下限');
            $table->decimal('pay_max_amount',10,2)->default(0)->comment('充值单笔上限');
            $table->decimal('collection_min_amount',10,2)->default(0)->comment('代付单笔下限');
            $table->decimal('collection_max_amount',10,2)->default(0)->comment('代付单笔上限');
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
        Schema::dropIfExists('merchant_channels');
    }
};
