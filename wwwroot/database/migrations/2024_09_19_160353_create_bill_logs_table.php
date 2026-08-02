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
        Schema::create('bill_logs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger("user_id")->default(0)->index();
            $table->bigInteger("telegram_group_id")->default(0)->index();
            $table->tinyInteger('type')->default(0)->comment('1=出，2=入');
            $table->decimal("amount",10,2)->default(0);
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
        Schema::dropIfExists('bill_logs');
    }
};
