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
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->bigInteger("telegram_group_id")->index()->default(0);
            $table->decimal("rate",10,2)->default(0);
            $table->decimal("ru_total_amount",10,2)->default(0)->comment("人民币");
            $table->decimal("chu_total_amount",10,2)->default(0)->comment("人民币");
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
        Schema::dropIfExists('bills');
    }
};
