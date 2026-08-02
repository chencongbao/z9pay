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
        Schema::create('report_user_agents', function (Blueprint $table) {
            $table->id();
            $table->date("date_add")->nullable()->index()->comment("统计日期");
            $table->integer("aid")->default(0)->index()->comment("代理ID");

            $table->decimal("deposit_commission",30,2)->default(0)->comment("代收佣金");
            $table->decimal("transfer_commission",30,2)->default(0)->comment("代付佣金");

            $table->integer("transfer_order_number_total")->default(0)->comment("总单数，成功");
            $table->integer("deposit_order_number_total")->default(0)->comment("总单数，成功");

            $table->decimal("deposit_order_total_amount",30,2)->default(0)->comment("代收总跑量，成功");
            $table->decimal("transfer_order_total_amount",30,2)->default(0)->comment("代付总跑量，成功");

            $table->decimal("jian_total_amount",30,2)->default(0)->comment("减项资金");
            $table->decimal("add_total_amount",30,2)->default(0)->comment("增项资金");

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
        Schema::dropIfExists('report_user_agents');
    }
};
