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
            $table->decimal('channel_rate',10,6)->default(0)->comment('渠道费率');
            $table->decimal('channel_cost',20,6)->default(0)->comment('渠道成本');
            $table->decimal('profit',20,6)->default(0)->comment('系统利润');
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
            if (Schema::hasColumn('transfer_orders', 'channel_rate')) {
                $table->dropColumn('channel_rate');
            }
            if (Schema::hasColumn('transfer_orders', 'channel_cost')) {
                $table->dropColumn('channel_cost');
            }
            if (Schema::hasColumn('transfer_orders', 'profit')) {
                $table->dropColumn('profit');
            }
        });
    }
};
