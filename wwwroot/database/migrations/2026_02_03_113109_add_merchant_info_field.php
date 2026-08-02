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
            $table->string('okx_payment_method',50)->nullable()->default('bank')->comment('支付方式');
            $table->string('okx_side',50)->nullable()->default('sell')->comment('模式');
            $table->string('okx_user_type',50)->nullable()->default('blockTrade')->comment('类型');
            $table->tinyInteger('okx_index')->default(2)->comment('第几当');
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
            if (Schema::hasColumn('merchant_infos', 'okx_payment_method')) {
                $table->dropColumn('okx_payment_method');
            }
            if (Schema::hasColumn('merchant_infos', 'okx_side')) {
                $table->dropColumn('okx_side');
            }
            if (Schema::hasColumn('merchant_infos', 'okx_user_type')) {
                $table->dropColumn('okx_user_type');
            }
            if (Schema::hasColumn('merchant_infos', 'okx_index')) {
                $table->dropColumn('okx_index');
            }
        });
    }
};
