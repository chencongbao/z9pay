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
        Schema::table('deposit_orders', function (Blueprint $table) {
            $table->decimal("usdt_ava_rate",10,6)->default(0)->comment("usdt平均费率");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('deposit_orders', function (Blueprint $table) {
            if (Schema::hasColumn('deposit_orders', 'usdt_ava_rate')) {
                $table->dropColumn('usdt_ava_rate');
            }
        });
    }
};
