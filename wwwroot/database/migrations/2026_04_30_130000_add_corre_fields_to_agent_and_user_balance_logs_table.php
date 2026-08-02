<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('agent_balance_logs', function (Blueprint $table) {
            $table->tinyInteger('is_corre')->default(0)->index()->comment('是否已冲正');
            $table->unsignedBigInteger('corre_log_id')->default(0)->index()->comment('冲正流水ID');
        });

        Schema::table('user_balance_logs', function (Blueprint $table) {
            $table->tinyInteger('is_corre')->default(0)->index()->comment('是否已冲正');
            $table->unsignedBigInteger('corre_log_id')->default(0)->index()->comment('冲正流水ID');
        });
    }

    public function down()
    {
        Schema::table('agent_balance_logs', function (Blueprint $table) {
            if (Schema::hasColumn('agent_balance_logs', 'is_corre')) {
                $table->dropColumn('is_corre');
            }
            if (Schema::hasColumn('agent_balance_logs', 'corre_log_id')) {
                $table->dropColumn('corre_log_id');
            }
        });

        Schema::table('user_balance_logs', function (Blueprint $table) {
            if (Schema::hasColumn('user_balance_logs', 'is_corre')) {
                $table->dropColumn('is_corre');
            }
            if (Schema::hasColumn('user_balance_logs', 'corre_log_id')) {
                $table->dropColumn('corre_log_id');
            }
        });
    }
};
