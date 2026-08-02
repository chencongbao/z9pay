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
            $table->decimal('deposit_agent3_rate',10,2)->default(0)->comment('代收三级代理费率');
            $table->decimal('agent3_rate',10,2)->default(0)->comment('默认三级代理费率');
            $table->decimal('transfer_agent3_rate',10,2)->default(0)->comment('代付三级代理费率');
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
            if (Schema::hasColumn('users', 'deposit_agent3_rate')) {
                $table->dropColumn('deposit_agent3_rate');
            }
            if (Schema::hasColumn('users', 'agent3_rate')) {
                $table->dropColumn('agent3_rate');
            }
            if (Schema::hasColumn('users', 'transfer_agent3_rate')) {
                $table->dropColumn('transfer_agent3_rate');
            }
        });
    }
};
