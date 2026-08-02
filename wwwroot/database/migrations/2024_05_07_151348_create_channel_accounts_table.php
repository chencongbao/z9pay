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
        Schema::create('channel_accounts', function (Blueprint $table) {
            $table->id();
            $table->integer('channel_id')->default(0)->index();
            $table->string('name',100)->nullable()->comment('名称');
            $table->tinyInteger('status')->default(0)->comment('状态');
            $table->decimal('pay_min_amount',10,2)->default(0)->comment('充值单笔下限');
            $table->decimal('pay_max_amount',10,2)->default(0)->comment('充值单笔上限');
            $table->decimal('pay_total_amount',10,2)->default(0)->comment('充值日总额');
            $table->decimal('collection_min_amount',10,2)->default(0)->comment('代付单笔下限');
            $table->decimal('collection_max_amount',10,2)->default(0)->comment('代付单笔上限');
            $table->decimal('collection_total_amount',10,2)->default(0)->comment('代付日总额');
            $table->text("params")->nullable()->comment("参数");
            $table->text("secret_params")->nullable()->comment("保密参数");
            $table->decimal('balance_amount',10,2)->default(0)->comment('余额');
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
        Schema::dropIfExists('channel_accounts');
    }
};
