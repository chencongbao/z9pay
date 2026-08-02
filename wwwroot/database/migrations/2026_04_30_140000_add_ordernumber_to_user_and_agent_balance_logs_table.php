<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('user_balance_logs', function (Blueprint $table) {
            $table->string('ordernumber', 100)->nullable()->index()->comment('订单号');
        });

        Schema::table('agent_balance_logs', function (Blueprint $table) {
            $table->string('ordernumber', 100)->nullable()->index()->comment('订单号');
        });
    }

    public function down()
    {
        Schema::table('user_balance_logs', function (Blueprint $table) {
            if (Schema::hasColumn('user_balance_logs', 'ordernumber')) {
                $table->dropColumn('ordernumber');
            }
        });

        Schema::table('agent_balance_logs', function (Blueprint $table) {
            if (Schema::hasColumn('agent_balance_logs', 'ordernumber')) {
                $table->dropColumn('ordernumber');
            }
        });
    }
};
