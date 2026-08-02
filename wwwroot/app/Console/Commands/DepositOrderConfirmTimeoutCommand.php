<?php

namespace App\Console\Commands;

use App\Models\DepositOrder;
use Illuminate\Console\Command;
use App\Jobs\DepositOrderTimeoutJob;

class DepositOrderConfirmTimeoutCommand extends Command
{
    protected $signature = 'deposit:confirm-timeout';

    protected $description = '定时检测金主代收订单确认订单是否超时';

    public function handle()
    {
        $timeoutMinutes = max(1, intval(bob_admin_setting('base_deposit_confirm_overtime')));
        $confirmBefore = time() - ($timeoutMinutes * 60);
        $count = 0;

        DepositOrder::query()
            ->whereIn('status', [3, 7])
            ->where('pay_status', 2)
            ->where('confirm_time', '>', 0)
            ->where('confirm_time', '<=', $confirmBefore)
            ->select(['id'])
            ->chunkById(1000, function ($orders) use (&$count) {
                foreach ($orders as $order) {
                    // 确认超时只派发确认场景，Job 内再次按状态和付款状态做幂等保护。
                    DepositOrderTimeoutJob::dispatch($order->id, 'confirm')->delay(now()->addSeconds(5))->onQueue('query');
                    $count++;
                }
            });

        $this->info("已派发代收确认超时订单处理任务：{$count}");

        return self::SUCCESS;
    }
}
