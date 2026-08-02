<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'income_settlement_to_deposit_on')) {
                $table->tinyInteger('income_settlement_to_deposit_on')->default(0)->comment('收益按天结算至保证金:1开启 0关闭')->after('commission_balance_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'income_settlement_to_deposit_on')) {
                $table->dropColumn('income_settlement_to_deposit_on');
            }
        });
    }
};
