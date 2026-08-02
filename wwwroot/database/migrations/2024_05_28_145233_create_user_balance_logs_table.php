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
        Schema::create('user_balance_logs', function (Blueprint $table) {
            $table->id();
            $table->integer('mid')->index()->default(0)->comment('商户ID');
            $table->integer('user_id')->index()->default(0)->comment('金主ID');
            $table->integer('action_user_id')->index()->default(0)->comment('金主ID');
            $table->decimal("amount",20,2)->default(0)->comment("金额");
            $table->tinyInteger('type')->default(0)->comment('类型');
            $table->integer('type_id')->index()->comment("类型ID");
            $table->string("remark")->nullable()->comment("备注");
            $table->decimal("balance_amount",20,2)->default(0)->comment("账户金额");
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
        Schema::dropIfExists('user_balance_logs');
    }
};
