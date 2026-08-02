<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'today_deposit_stat_date')) {
                $table->date('today_deposit_stat_date')->nullable()->after('round_times')->comment('今日代收统计日期');
            }
            if (!Schema::hasColumn('users', 'today_deposit_total_number')) {
                $table->unsignedInteger('today_deposit_total_number')->default(0)->after('today_deposit_stat_date')->comment('今日代收成功笔数');
            }
            if (!Schema::hasColumn('users', 'today_deposit_total_amount')) {
                $table->decimal('today_deposit_total_amount', 30, 2)->default(0)->after('today_deposit_total_number')->comment('今日代收成功跑量');
            }
            if (!Schema::hasColumn('users', 'today_deposit_total_income')) {
                $table->decimal('today_deposit_total_income', 30, 2)->default(0)->after('today_deposit_total_amount')->comment('今日代收收益');
            }
            if (!Schema::hasColumn('users', 'today_transfer_stat_date')) {
                $table->date('today_transfer_stat_date')->nullable()->after('today_deposit_total_income')->comment('今日代付统计日期');
            }
            if (!Schema::hasColumn('users', 'today_transfer_total_number')) {
                $table->unsignedInteger('today_transfer_total_number')->default(0)->after('today_transfer_stat_date')->comment('今日代付成功笔数');
            }
            if (!Schema::hasColumn('users', 'today_transfer_total_amount')) {
                $table->decimal('today_transfer_total_amount', 30, 2)->default(0)->after('today_transfer_total_number')->comment('今日代付成功跑量');
            }
            if (!Schema::hasColumn('users', 'today_transfer_total_income')) {
                $table->decimal('today_transfer_total_income', 30, 2)->default(0)->after('today_transfer_total_amount')->comment('今日代付收益');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('users', 'today_deposit_stat_date') ? 'today_deposit_stat_date' : null,
                Schema::hasColumn('users', 'today_deposit_total_number') ? 'today_deposit_total_number' : null,
                Schema::hasColumn('users', 'today_deposit_total_amount') ? 'today_deposit_total_amount' : null,
                Schema::hasColumn('users', 'today_deposit_total_income') ? 'today_deposit_total_income' : null,
                Schema::hasColumn('users', 'today_transfer_stat_date') ? 'today_transfer_stat_date' : null,
                Schema::hasColumn('users', 'today_transfer_total_number') ? 'today_transfer_total_number' : null,
                Schema::hasColumn('users', 'today_transfer_total_amount') ? 'today_transfer_total_amount' : null,
                Schema::hasColumn('users', 'today_transfer_total_income') ? 'today_transfer_total_income' : null,
            ]);

            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
