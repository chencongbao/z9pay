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
        Schema::create('channel_rates', function (Blueprint $table) {
            $table->id();
            $table->integer('channel_id')->default(0)->index();
            $table->integer('payment_id')->default(0)->comment('支付ID');
            $table->decimal('rate',10,2)->default('0');
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
        Schema::dropIfExists('channel_rates');
    }
};
