<?php

namespace App\Console\Commands;

use App\Models\DepositOrder;
use Illuminate\Console\Command;
use App\Jobs\DepositOrderTimeoutJob;

class DepositOrderPaymentTimeoutCommand extends Command
{
    protected $signature = 'deposit:payment-timeout';

    protected $description = '定时检测代收订单支付超时';

    public function handle()
    {
        $now = time();
        $count = 0;

        DepositOrder::query()
            ->where('expired_time', '>', 0)
            ->where('expired_time', '<', $now)
            ->whereIn('status', [1, 3])
            ->whereIn('pay_status', [1, 3])
            ->select(['id'])
            ->chunkById(1000, function ($orders) use (&$count) {
                foreach ($orders as $order) {
                    // 超时状态由队列统一处理，Job 内部再做幂等状态保护。
                    DepositOrderTimeoutJob::dispatch($order->id)->delay(now()->addSeconds(5))->onQueue('query');
                    $count++;
                }
            });

        $this->info("已派发代收支付超时订单处理任务：{$count}");

        return self::SUCCESS;
    }
}
