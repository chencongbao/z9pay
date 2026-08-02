<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 渠道银行编码
     *
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('channel_bank_codes', function (Blueprint $table) {
            $table->id();
            $table->integer('bank_code_id')->index()->default(0)->comment("所属银行");
            $table->string('code',20)->index();
            $table->integer('channel_id')->index()->default(0)->comment("所属银行");
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
        Schema::dropIfExists('channel_bank_codes');
    }
};
