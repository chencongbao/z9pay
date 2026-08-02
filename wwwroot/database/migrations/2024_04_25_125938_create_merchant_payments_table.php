<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMerchantPaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('merchant_payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('merchant_user_id')->index()->default('0');
            $table->integer('merchant_agent_user_id')->index()->default('0');
            $table->integer('payment_id')->index()->default('0');
            $table->tinyInteger('status')->default('0');
            $table->decimal('pay_rate')->default('0');
            $table->decimal('agent_rate')->default('0');
            $table->decimal('min_limit_amount')->default('0');
            $table->decimal('max_limit_amount')->default('0');
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
        Schema::dropIfExists('merchant_payments');
    }
}
