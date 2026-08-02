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
        Schema::table('merchant_balance_logs', function (Blueprint $table) {
            $table->decimal("usdt_rate",20,6)->default(0);
            $table->decimal("usdt_amount",20,6)->default(0);
            $table->decimal("usdt_balance_amount",20,6)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('merchant_balance_logs', function (Blueprint $table) {
            if (Schema::hasColumn('merchant_balance_logs', 'usdt_rate')) {
                $table->dropColumn('usdt_rate');
            }
            if (Schema::hasColumn('merchant_balance_logs', 'usdt_amount')) {
                $table->dropColumn('usdt_amount');
            }
            if (Schema::hasColumn('merchant_balance_logs', 'usdt_balance_amount')) {
                $table->dropColumn('usdt_balance_amount');
            }
        });
    }
};
