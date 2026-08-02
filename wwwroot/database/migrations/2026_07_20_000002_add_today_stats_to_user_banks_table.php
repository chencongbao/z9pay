<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_banks', function (Blueprint $table) {
            $table->date('today_stat_date')->nullable()->after('last_collection_time')->comment('今日统计日期');
            $table->decimal('today_total_amount', 30, 2)->default(0)->after('today_stat_date')->comment('今日成功入款金额');
            $table->unsignedInteger('today_total_number')->default(0)->after('today_total_amount')->comment('今日成功入款笔数');
            $table->decimal('today_total_income', 30, 2)->default(0)->after('today_total_number')->comment('今日收款收益');
        });
    }

    public function down(): void
    {
        Schema::table('user_banks', function (Blueprint $table) {
            $table->dropColumn(['today_stat_date', 'today_total_amount', 'today_total_number', 'today_total_income']);
        });
    }
};
