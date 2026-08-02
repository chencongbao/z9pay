<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('merchant_infos', function (Blueprint $table) {
            $table->integer('merchant_user_id')->index()->default(0);
            $table->integer('agent_user_id')->index()->default(0);
            $table->string("coder", 50)->unique();
            $table->string("appkey", 50)->unique();
            $table->string("appsecret", 100)->unique();
            $table->text('pay_white_ip')->nullable();
            $table->text('withdraw_white_ip')->nullable();
            $table->text('callback_white_ip')->nullable();
            $table->tinyInteger('amount_float_type')->default(0);
            $table->decimal('float_amount', 10, 2)->default(0);
            $table->tinyInteger('currency_id')->default(0);
            $table->decimal('balance_amount', 10, 2)->default(0);
            $table->string('name',100)->nullable();
            $table->string('deposits_callback_url',255)->nullable();
            $table->string('transfer_callback_url',255)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('merchant_infos');
    }
};
