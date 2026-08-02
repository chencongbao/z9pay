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
        Schema::table('channels', function (Blueprint $table) {
            $table->tinyInteger('deposit_order_query')->default(0)->comment('充值查询');
            $table->tinyInteger('transfer_order_query')->default(0)->comment('充值查询');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('channels', function (Blueprint $table) {
            if (Schema::hasColumn('channels', 'deposit_order_query')) {
                $table->dropColumn('deposit_order_query');
            }
            if (Schema::hasColumn('channels', 'transfer_order_query')) {
                $table->dropColumn('transfer_order_query');
            }
        });
    }
};
