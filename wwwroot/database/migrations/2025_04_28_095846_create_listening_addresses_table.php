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
        Schema::create('listening_addresses', function (Blueprint $table) {
            $table->id();
            $table->string("address",255)->nullable()->comment("地址")->index();
            $table->decimal("trx_balance",30,6)->default(0);
            $table->decimal("usdt_balance",30,6)->default(0);
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
        Schema::dropIfExists('listening_addresses');
    }
};
