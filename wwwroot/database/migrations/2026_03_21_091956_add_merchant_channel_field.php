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
            $table->integer('weight')->default(1)->comment('权重');
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
            if (Schema::hasColumn('merchant_channels', 'weight')) {
                $table->dropColumn('weight');
            }
        });
    }
};
