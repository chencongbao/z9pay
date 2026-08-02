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
        Schema::create('agent_balance_logs', function (Blueprint $table) {
            $table->id();
            $table->integer('mid')->index()->default(0)->comment('商户ID');
            $table->integer('agent_id')->index()->default(0)->comment('代理ID');
            $table->integer('action_agent_id')->index()->default(0)->comment('操作人ID');
            $table->decimal("amount",20,2)->default(0)->comment("金额");
            $table->tinyInteger('type')->default(0)->comment('类型');
            $table->integer('type_id')->index()->comment("类型ID");
            $table->string("remark")->nullable()->comment("备注");
            $table->decimal("balance_amount",20,2)->default(0)->comment("账户金额");
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
        Schema::dropIfExists('agent_balance_logs');
    }
};
