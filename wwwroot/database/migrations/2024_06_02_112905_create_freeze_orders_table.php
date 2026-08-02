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
        Schema::create('freeze_orders', function (Blueprint $table) {
            $table->id();
            $table->integer("mid")->index()->default(0)->comment("充值订单ID");
            $table->integer("user_id")->index()->default(0)->comment("充值订单ID");
            $table->integer("deposit_order_id")->index()->default(0)->comment("充值订单ID");
            $table->tinyInteger('status')->default(0)->comment("冻结状态,1=冻结，0=解冻");
            $table->decimal("amount",10,2)->default(0)->comment("冻结金额");
            $table->integer("unfreeze_time")->default(0)->comment("解冻时间");
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
        Schema::dropIfExists('freeze_orders');
    }
};
