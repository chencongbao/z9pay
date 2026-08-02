<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bill_logs', function (Blueprint $table) {
            $table->string('original_currency', 8)->nullable()->after('amount')->comment('原始币种：CNY或USDT');
            $table->decimal('original_amount', 20, 6)->nullable()->after('original_currency')->comment('用户输入的原始金额');
            $table->decimal('exchange_rate', 20, 6)->nullable()->after('original_amount')->comment('该笔汇率：1USDT兑换CNY');
            $table->decimal('fee_rate', 10, 6)->nullable()->after('exchange_rate')->comment('该笔费率百分比');
            $table->decimal('payable_amount', 20, 6)->nullable()->after('fee_rate')->comment('入款扣费后的应下发CNY金额');
            $table->unsignedTinyInteger('calculation_version')->default(0)->after('payable_amount')->comment('0=历史兼容，1=逐笔快照');
        });

        $processedGroups = [];
        DB::table('bills')->select(['id', 'telegram_group_id', 'rate', 'rate1'])->orderBy('id')->chunkById(100, function ($bills) use (&$processedGroups) {
            foreach ($bills as $bill) {
                $groupKey = (string)$bill->telegram_group_id;
                if (isset($processedGroups[$groupKey])) {
                    continue;
                }
                $processedGroups[$groupKey] = true;
                $feeRate = (float)$bill->rate1;

                DB::table('bill_logs')->where('telegram_group_id', $bill->telegram_group_id)->whereNull('exchange_rate')->update([
                    'original_currency' => 'CNY',
                    'original_amount' => DB::raw('amount'),
                    'exchange_rate' => (float)$bill->rate,
                    'fee_rate' => $feeRate,
                    'payable_amount' => DB::raw('CASE WHEN type = 1 THEN ROUND(amount * (100 - ' . $feeRate . ') / 100, 2) ELSE NULL END'),
                    'calculation_version' => 0,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('bill_logs', function (Blueprint $table) {
            $table->dropColumn(['original_currency', 'original_amount', 'exchange_rate', 'fee_rate', 'payable_amount', 'calculation_version']);
        });
    }
};
