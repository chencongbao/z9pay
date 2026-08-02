<?php

namespace App\Console\Commands;

use App\Models\TransferOrder;
use Illuminate\Console\Command;
use App\Jobs\TransferOrderPaymentTimeoutJob;

class TransferOrderPaymentTimeoutCommand extends Command
{
    protected $signature = 'transfer:payment-timeout';

    protected $description = '定时检测金主代付订单支付超时';

    public function handle()
    {
        $timeoutMinutes = max(1, intval(bob_admin_setting('base_transfer_pay_overtime')));
        $timeoutBefore = now()->subMinutes($timeoutMinutes);
        $count = 0;

        TransferOrder::query()
            ->where('status', 2)
            ->where('pay_status', 1)
            ->where('user_id', '>', 0)
            ->where('updated_at', '<=', $timeoutBefore)
            ->select(['id'])
            ->chunkById(1000, function ($orders) use (&$count) {
                foreach ($orders as $order) {
                    // Job 内重新锁定并校验状态和超时时间，避免与提交付款并发覆盖。
                    TransferOrderPaymentTimeoutJob::dispatch($order->id)->delay(now()->addSeconds(5))->onQueue('query');
                    $count++;
                }
            });

        $this->info("已派发金主代付超时订单处理任务：{$count}");

        return self::SUCCESS;
    }
}
