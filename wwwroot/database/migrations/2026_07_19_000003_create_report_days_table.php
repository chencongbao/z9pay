<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('report_days', function (Blueprint $table) {
            $table->id();
            $table->date('date_add')->nullable()->unique()->comment('统计日期');

            $table->integer('deposit_order_number_total')->default(0)->comment('代收总单数');
            $table->integer('deposit_order_number_success')->default(0)->comment('代收成功单数');
            $table->integer('deposit_order_number_fail')->default(0)->comment('代收失败单数');
            $table->integer('deposit_order_number_overtime')->default(0)->comment('代收超时单数');
            $table->integer('deposit_order_number_swiping')->default(0)->comment('代收刷单单数');

            $table->integer('transfer_order_number_total')->default(0)->comment('代付总单数');
            $table->integer('transfer_order_number_success')->default(0)->comment('代付成功单数');
            $table->integer('transfer_order_number_fail')->default(0)->comment('代付失败单数');

            $table->integer('settlement_order_number_total')->default(0)->comment('结算总单数');
            $table->integer('settlement_order_number_success')->default(0)->comment('结算成功单数');
            $table->integer('settlement_order_number_fail')->default(0)->comment('结算失败单数');

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('report_days');
    }
};
