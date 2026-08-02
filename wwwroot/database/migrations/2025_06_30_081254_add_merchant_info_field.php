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
            $table->decimal("settlement_amount",30,2)->default(0)->comment("结算金额");
            $table->decimal("freeze_amount",30,2)->default(0)->comment("冻结金额");
            $table->tinyInteger('deposit_channel_mode')->default(0)->comment('渠道匹配模式');
            $table->tinyInteger('transfer_channel_mode')->default(0)->comment('渠道匹配模式');
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
            if (Schema::hasColumn('merchant_infos', 'freeze_amount')) {
                $table->dropColumn('freeze_amount');
            }
            if (Schema::hasColumn('merchant_infos', 'settlement_amount')) {
                $table->dropColumn('settlement_amount');
            }
            if (Schema::hasColumn('merchant_infos', 'deposit_channel_mode')) {
                $table->dropColumn('deposit_channel_mode');
            }
            if (Schema::hasColumn('merchant_infos', 'transfer_channel_mode')) {
                $table->dropColumn('transfer_channel_mode');
            }
        });
    }
};
