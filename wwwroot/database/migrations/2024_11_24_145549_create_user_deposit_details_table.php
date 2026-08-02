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
        Schema::create('user_deposit_details', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id')->index()->default(0)->comment('金主ID');
            $table->decimal("amount",20,2)->default(0)->comment("金额");
            $table->integer('admin_id')->index()->default(0)->comment('操作人ID');
            $table->string('remark',255)->nullable()->comment('备注');
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
        Schema::dropIfExists('user_deposit_details');
    }
};
