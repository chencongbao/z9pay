<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Jobs\MerchantBalanceSnapshotJob;

class MerchantBalanceSnapshotCommand extends Command
{
    protected $signature = 'merchant:balance-snapshot';

    protected $description = '更新商户每日余额快照';

    public function handle(): int
    {
        $total = 0;

        // 只读取派发 Job 需要的字段，避免模型水合和逐行额外属性计算。
        DB::table('merchant_infos as mi')
            ->join(config('merchant-admin.database.users_table', 'merchant_users') . ' as mu', 'mu.id', '=', 'mi.merchant_user_id')
            ->select(['mi.merchant_user_id', 'mi.available_balance', 'mi.available_usdt_balance'])
            ->where('mu.status', 1)
            ->whereNull('mi.deleted_at')
            ->whereNull('mu.deleted_at')
            ->orderBy('mi.merchant_user_id')
            ->chunkById(1000, function ($merchants) use (&$total) {
                foreach ($merchants as $merchant) {
                    MerchantBalanceSnapshotJob::dispatch($merchant->merchant_user_id, $merchant->available_balance, $merchant->available_usdt_balance)->onQueue('query');
                    $total++;
                }
            }, 'mi.merchant_user_id', 'merchant_user_id');

        $this->info("商户每日余额快照更新任务派发完成，任务数：{$total}");
        return 0;
    }
}
