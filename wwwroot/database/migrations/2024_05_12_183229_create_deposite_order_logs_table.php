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
        Schema::create('deposite_order_logs', function (Blueprint $table) {
            $table->id();
            $table->integer('order_id')->default(0)->index()->comment('订单ID');
            $table->string("type","50")->nullable()->comment("类型");
            $table->string("message","255")->nullable()->comment("消息");
            $table->text("content")->nullable()->comment("详情");
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
        Schema::dropIfExists('deposite_order_logs');
    }
};
