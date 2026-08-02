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
            $table->decimal('settlement_user_rate',10,2)->default(0)->comment('结算金主费率');
            $table->decimal('settlement_agent2_rate',10,2)->default(0)->comment('结算二级代理费率');
            $table->decimal('settlement_agent1_rate',10,2)->default(0)->comment('结算一级代理费率');
            $table->decimal('settlement_agent3_rate',10,2)->default(0)->comment('结算三级代理费率');
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
            if (Schema::hasColumn('users', 'settlement_user_rate')) {
                $table->dropColumn('settlement_user_rate');
            }
            if (Schema::hasColumn('users', 'settlement_agent2_rate')) {
                $table->dropColumn('settlement_agent2_rate');
            }
            if (Schema::hasColumn('users', 'settlement_agent1_rate')) {
                $table->dropColumn('settlement_agent1_rate');
            }
            if (Schema::hasColumn('users', 'settlement_agent3_rate')) {
                $table->dropColumn('settlement_agent3_rate');
            }
        });
    }
};
