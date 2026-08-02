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
        Schema::table('transfer_orders', function (Blueprint $table) {
            $table->string('channel_info',255)->nullable()->comment('代付渠道');
            $table->string("transfer_order_confirm_remark",255)->nullable()->comment("代付确认人信息");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transfer_orders', function (Blueprint $table) {
            if (Schema::hasColumn('transfer_orders', 'channel_info')) {
                $table->dropColumn('channel_info');
            }
            if (Schema::hasColumn('transfer_orders', 'transfer_order_confirm_remark')) {
                $table->dropColumn('transfer_order_confirm_remark');
            }
        });
    }
};
