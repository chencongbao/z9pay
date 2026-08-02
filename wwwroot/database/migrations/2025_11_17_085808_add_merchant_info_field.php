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
        Schema::table('merchant_infos', function (Blueprint $table) {
            $table->decimal("usdt_float_rate",10,6)->default(0)->comment("usdt浮动费率");
            $table->decimal("default_usdt_ava_rate",10,6)->default(0)->comment("usdt默认平均费率");
            $table->decimal("usdt_ava_rate",10,6)->default(0)->comment("usdt平均费率");
            $table->tinyInteger('is_usdt_ava_rate')->default(0)->comment('开启usdt平均费率计算');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('merchant_infos', function (Blueprint $table) {
            if (Schema::hasColumn('merchant_infos', 'usdt_float_rate')) {
                $table->dropColumn('usdt_float_rate');
            }
            if (Schema::hasColumn('merchant_infos', 'usdt_ava_rate')) {
                $table->dropColumn('usdt_ava_rate');
            }
            if (Schema::hasColumn('merchant_infos', 'default_usdt_ava_rate')) {
                $table->dropColumn('default_usdt_ava_rate');
            }
            if (Schema::hasColumn('merchant_infos', 'is_usdt_ava_rate')) {
                $table->dropColumn('is_usdt_ava_rate');
            }
        });
    }
};
