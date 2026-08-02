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
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('agent4_rate',10,2)->default(0)->comment('默认4级代理费率');
            $table->decimal('agent5_rate',10,2)->default(0)->comment('默认5级代理费率');
            $table->decimal('deposit_agent4_rate',10,2)->default(0)->comment('代收4级代理费率');
            $table->decimal('deposit_agent5_rate',10,2)->default(0)->comment('代收5级代理费率');
            $table->decimal('transfer_agent4_rate',10,2)->default(0)->comment('代付4级代理费率');
            $table->decimal('transfer_agent5_rate',10,2)->default(0)->comment('代付5级代理费率');
            $table->decimal('settlement_agent4_rate',10,2)->default(0)->comment('结算4级代理费率');
            $table->decimal('settlement_agent5_rate',10,2)->default(0)->comment('结算5级代理费率');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'agent4_rate')) {
                $table->dropColumn('agent4_rate');
            }
            if (Schema::hasColumn('users', 'agent5_rate')) {
                $table->dropColumn('agent5_rate');
            }
            if (Schema::hasColumn('users', 'deposit_agent4_rate')) {
                $table->dropColumn('deposit_agent4_rate');
            }
            if (Schema::hasColumn('users', 'deposit_agent5_rate')) {
                $table->dropColumn('deposit_agent5_rate');
            }
            if (Schema::hasColumn('users', 'transfer_agent4_rate')) {
                $table->dropColumn('transfer_agent4_rate');
            }
            if (Schema::hasColumn('users', 'transfer_agent5_rate')) {
                $table->dropColumn('transfer_agent5_rate');
            }
            if (Schema::hasColumn('users', 'settlement_agent4_rate')) {
                $table->dropColumn('settlement_agent4_rate');
            }
            if (Schema::hasColumn('users', 'settlement_agent5_rate')) {
                $table->dropColumn('settlement_agent5_rate');
            }
        });
    }
};
