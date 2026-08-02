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
        Schema::create('report_user_banks', function (Blueprint $table) {
            $table->id();
            $table->date("date_add")->nullable()->index()->comment("统计日期");
            $table->integer("uid")->default(0)->index()->comment("金主ID");
            $table->integer("ubid")->default(0)->index()->comment("卡ID");

            $table->integer("deposit_order_number_total")->default(0)->comment("总单数");
            $table->integer("deposit_order_number_success")->default(0)->comment("成功单数");
            $table->integer("deposit_order_number_fail")->default(0)->comment("失败单数");
            $table->integer("deposit_order_number_overtime")->default(0)->comment("超时单数");
            $table->decimal("deposit_order_total_amount",30,2)->default(0)->comment("代收总跑量，成功单子总代收");
            $table->decimal("deposit_order_total_fee",30,2)->default(0)->comment("代收总手续费，成功单子总代收");

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
        Schema::dropIfExists('report_user_banks');
    }
};
