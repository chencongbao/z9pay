<?php

namespace App\Jobs;

use App\Models\TransferOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\App;
use App\Services\Report\OrderStatusReportRepairService;
use Illuminate\Support\Facades\DB;
use Illuminate\Queue\SerializesModels;
use App\Services\Const\LogConstService;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use App\Services\TransferOrderLog\CreateTransferOrderLogService;

class TransferOrderPaymentTimeoutJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $timeout = 120;

    public $uniqueFor = 600;

    public $order_id;

    public $timeout_minutes;

    public function __construct($orderId = 0, $timeoutMinutes = null)
    {
        $this->order_id = intval($orderId);
        $this->timeout_minutes = $timeoutMinutes === null ? null : max(1, intval($timeoutMinutes));
    }

    public function uniqueId(): string
    {
        return (string) $this->order_id;
    }

    public function handle(): void
    {
        $order = DB::transaction(function () {
            $order = TransferOrder::query()
                ->whereKey($this->order_id)
                ->where('status', 2)
                ->where('pay_status', 1)
                ->where('user_id', '>', 0)
                ->lockForUpdate()
                ->first(['id', 'type', 'status', 'pay_status', 'user_id', 'ordernumber', 'updated_at']);
            if (!$order || $order->updated_at->timestamp > $this->timeoutBefore()) {
                return null;
            }

            $ownerId = (int) $order->user_id;
            $order->status = (int) $order->type === 1 ? 6 : 3;
            $order->remark = "【金主ID：{$ownerId}】代付超时";
            $order->user_id = 0;
            $order->pay_status = 0;
            $order->save();

            App::makeWith(CreateTransferOrderLogService::class, [
                'filename' => LogConstService::TRANSFER_ORDER_LOG_PREFIX . $order->id,
            ])->excute($order->id, '金主代付超时', "金主ID：{$ownerId}");

            return $order;
        });

        if ($order) {
            cache_transfer_info($order);
            App::make(OrderStatusReportRepairService::class)->forTransferOrder($order);
        }
    }

    private function timeoutBefore(): int
    {
        $minutes = $this->timeout_minutes ?? max(1, intval(bob_admin_setting('base_transfer_pay_overtime')));

        return time() - ($minutes * 60);
    }
}
