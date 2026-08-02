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
            $table->decimal('deposit_user_rate',10,2)->default(0)->comment('代收金主费率');
            $table->decimal('deposit_agent2_rate',10,2)->default(0)->comment('代收二级代理费率');
            $table->decimal('deposit_agent1_rate',10,2)->default(0)->comment('代收一级代理费率');
            $table->decimal('transfer_user_rate',10,2)->default(0)->comment('代付金主费率');
            $table->decimal('transfer_agent1_rate',10,2)->default(0)->comment('代付一级代理费率');
            $table->decimal('transfer_agent2_rate',10,2)->default(0)->comment('代付二级代理费率');
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
            if (Schema::hasColumn('users', 'deposit_user_rate')) {
                $table->dropColumn('deposit_user_rate');
            }
            if (Schema::hasColumn('users', 'deposit_agent2_rate')) {
                $table->dropColumn('deposit_agent2_rate');
            }
            if (Schema::hasColumn('users', 'deposit_agent1_rate')) {
                $table->dropColumn('deposit_agent1_rate');
            }
            if (Schema::hasColumn('users', 'transfer_user_rate')) {
                $table->dropColumn('transfer_user_rate');
            }
            if (Schema::hasColumn('users', 'transfer_agent1_rate')) {
                $table->dropColumn('transfer_agent1_rate');
            }
            if (Schema::hasColumn('users', 'transfer_agent2_rate')) {
                $table->dropColumn('transfer_agent2_rate');
            }
        });
    }
};
