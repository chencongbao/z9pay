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
        Schema::create('black_contents', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('type')->default(0)->comment('1：ip黑名单，2:付款人姓名黑名单');
            $table->string("content",255)->nullable()->comment("内容");
            $table->tinyInteger("status")->default(0)->comment("状态");
            $table->string("remark")->nullable()->comment("备注");
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
        Schema::dropIfExists('black_contents');
    }
};
