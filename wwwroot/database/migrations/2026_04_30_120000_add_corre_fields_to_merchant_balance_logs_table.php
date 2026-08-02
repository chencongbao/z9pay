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
            $table->tinyInteger('is_corre')->default(0)->comment('是否已冲正');
            $table->unsignedBigInteger('corre_log_id')->default(0)->comment('冲正流水ID');
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
            if (Schema::hasColumn('merchant_balance_logs', 'is_corre')) {
                $table->dropColumn('is_corre');
            }

            if (Schema::hasColumn('merchant_balance_logs', 'corre_log_id')) {
                $table->dropColumn('corre_log_id');
            }
        });
    }
};
