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
        Schema::create('merchant_agent_reports', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id')->default(0)->index();
            $table->date('date_add')->nullable()->index()->comment("日期");
            $table->decimal("deposit_total_amount",20,2)->default(0)->comment("代收跑量");
            $table->decimal("transfer_total_amount",20,2)->default(0)->comment("代付跑量");
            $table->decimal("total_amount",20,2)->default(0)->comment("总跑量");
            $table->decimal("deposit_total_income",20,2)->default(0)->comment("代收佣金");
            $table->decimal("transfer_total_income",20,2)->default(0)->comment("代付佣金");
            $table->decimal("total_income",20,2)->default(0)->comment("总佣金");
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
        Schema::dropIfExists('merchant_agent_reports');
    }
};
