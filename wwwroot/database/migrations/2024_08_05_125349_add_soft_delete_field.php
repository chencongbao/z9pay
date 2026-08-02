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
        Schema::table('merchant_infos', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('merchant_users', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('merchant_payments', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('merchant_channels', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('merchant_balance_logs', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('merchant_trade_logs', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('agent_users', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('agent_balance_logs', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('user_balance_logs', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('user_banks', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('user_trade_logs', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('merchant_infos', function (Blueprint $table) {
            if (Schema::hasColumn('merchant_infos', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
        Schema::table('merchant_users', function (Blueprint $table) {
            if (Schema::hasColumn('merchant_users', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
        Schema::table('merchant_payments', function (Blueprint $table) {
            if (Schema::hasColumn('merchant_payments', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
        Schema::table('merchant_channels', function (Blueprint $table) {
            if (Schema::hasColumn('merchant_channels', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
        Schema::table('merchant_balance_logs', function (Blueprint $table) {
            if (Schema::hasColumn('merchant_balance_logs', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
        Schema::table('merchant_trade_logs', function (Blueprint $table) {
            if (Schema::hasColumn('merchant_trade_logs', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('agent_users', function (Blueprint $table) {
            if (Schema::hasColumn('agent_users', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('agent_balance_logs', function (Blueprint $table) {
            if (Schema::hasColumn('agent_balance_logs', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });


        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
        Schema::table('user_balance_logs', function (Blueprint $table) {
            if (Schema::hasColumn('user_balance_logs', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
        Schema::table('user_banks', function (Blueprint $table) {
            if (Schema::hasColumn('user_banks', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
        Schema::table('user_trade_logs', function (Blueprint $table) {
            if (Schema::hasColumn('user_trade_logs', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
