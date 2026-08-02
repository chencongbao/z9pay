<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('user_balance_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('user_balance_logs', 'user_bank_id')) {
                $table->unsignedBigInteger('user_bank_id')
                    ->default(0)
                    ->after('user_id')
                    ->comment('金主收款卡ID');
            }
        });
    }

    public function down()
    {
        Schema::table('user_balance_logs', function (Blueprint $table) {
            if (Schema::hasColumn('user_balance_logs', 'user_bank_id')) {
                $table->dropColumn('user_bank_id');
            }
        });
    }
};
