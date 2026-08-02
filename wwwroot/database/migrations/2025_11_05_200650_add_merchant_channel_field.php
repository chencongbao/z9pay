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
        Schema::table('merchant_channels', function (Blueprint $table) {
            $table->tinyInteger('use_cashier')->default(0)->comment('0=默认，1=使用，2=不使用，是否使用系统收营台');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('merchant_channels', function (Blueprint $table) {
            if (Schema::hasColumn('merchant_channels', 'use_cashier')) {
                $table->dropColumn('use_cashier');
            }
        });
    }
};
