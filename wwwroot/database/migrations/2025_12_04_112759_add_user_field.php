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
            $table->text("user_deposit_payment_rate")->nullable()->comment("金主针对代收编码设置费率");
            $table->tinyInteger('action_limit_payment')->default(0)->comment('操作通道编码限制');
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
            if (Schema::hasColumn('users', 'action_limit_payment')) {
                $table->dropColumn('action_limit_payment');
            }
            if (Schema::hasColumn('users', 'user_deposit_payment_rate')) {
                $table->dropColumn('user_deposit_payment_rate');
            }
        });
    }
};
