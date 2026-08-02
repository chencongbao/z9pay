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
        Schema::create('group_addresses', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('chat_id');
            $table->string('address');
            $table->unsignedInteger('count')->default(0); // 出现次数
            $table->timestamps();

            $table->unique(['chat_id', 'address']); // 同一个群 + 地址 唯一
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('group_addresses');
    }
};
