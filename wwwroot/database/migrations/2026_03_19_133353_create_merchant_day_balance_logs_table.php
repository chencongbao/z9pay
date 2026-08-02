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
        Schema::create('merchant_day_balance_logs', function (Blueprint $table) {
            $table->id();
            $table->integer("mid")->index()->default(0);
            $table->date("date_add")->index()->nullable();
            $table->decimal('balance_amount',"30","8")->default(0);
            $table->decimal('usdt_balance_amount',"30","8")->default(0);
            $table->timestamps();
            $table->unique(['mid', 'date_add'], 'merchant_day_balance_logs_mid_date_add_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('merchant_day_balance_logs');
    }
};
