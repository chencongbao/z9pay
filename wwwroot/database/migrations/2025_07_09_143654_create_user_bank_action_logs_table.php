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
        Schema::create('user_bank_action_logs', function (Blueprint $table) {
            $table->id();
            $table->integer("user_bank_id")->default(0)->index()->comment("收款卡ID");
            $table->tinyInteger("action")->default(0)->comment("1=添加，2=修改，3=删除，4=还原，5=收款开启，6=收款关闭,7=批量删除,8=确认付款");
            $table->string("name",255)->nullable()->comment("名称");
            $table->tinyInteger("type")->default(0)->comment("1=金主，2=系统,3，金主代理");
            $table->integer("type_id")->default(0)->index()->comment("用户ID");
            $table->text("remark")->nullable()->comment("备注");
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
        Schema::dropIfExists('user_bank_action_logs');
    }
};
