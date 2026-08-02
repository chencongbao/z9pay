<?php

namespace App\Jobs;

use Throwable;
use App\Models\Channel;
use App\Models\TransferOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use App\Services\Report\OrderStatusReportRepairService;
use Illuminate\Queue\SerializesModels;
use App\Services\Const\LogConstService;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\SystemNotice\SystemNoticeService;
use App\Services\TransferOrder\TransferOrderFailService;
use App\Services\TransferOrderLog\CreateTransferOrderLogService;

class QueryTransferOrderSubmitSuccessJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const DUPLICATE_LOCK_SECONDS = 30;

    public $tries = 1;

    public $timeout = 120;

    public $id = 0;

    public function __construct($order_id = 0)
    {
        $this->id = $order_id;
    }

    public function handle()
    {
        if ($this->id <= 0 || !Cache::add($this->duplicateLockKey(), 1, now()->addSeconds(self::DUPLICATE_LOCK_SECONDS))) {
            return;
        }

        try {
            $this->handleQuery();
        } catch (Throwable $e) {
            $this->noticeException($e);
        }
    }

    private function handleQuery(): void
    {
        $order = TransferOrder::where('id', $this->id)->where('status', 2)->first(['id', 'type', 'status', 'remark', 'ordernumber', 'channel_id']);
        if (!$order) {
            return;
        }

        $channel = Channel::find($order->channel_id, ['id', 'classname']);
        $classname = 'Richard\\Payment\\Channel\\' . optional($channel)->classname;
        if (empty(optional($channel)->classname) || !class_exists($classname)) {
            throw new \Exception('代付提交确认渠道类不存在，订单ID：' . $order->id);
        }

        $payment = new $classname(LogConstService::TRANSFER_ORDER_LOG_PREFIX . $order->id);
        if ($payment->queryTransferOrderExist($order->ordernumber, 0)) {
            return;
        }

        $handled = false;
        if ($order->type == 0) {
            if (intval(config('other.transfer_pending_status', 1)) == 1) {
                $handled = $this->failPendingOrder($order->id);
                if ($handled) {
                    bob_send_system_transfer_notice(['success_text' => '代付订单处理中，订单号：' . $order->ordernumber, 'voice_id' => 'transfer_6', 'id' => 6]);
                }
            } else {
                App::makeWith(TransferOrderFailService::class, ['filename' => LogConstService::TRANSFER_ORDER_LOG_PREFIX . $order->id])->excute($order->id, '订单未提交到渠道，系统自动判定失败');
                $handled = true;
            }
        }
        if ($order->type == 1) {
            $handled = $this->failPendingOrder($order->id);
        }

        if (!$handled) {
            return;
        }

        $createTransferOrderLogService = App::makeWith(CreateTransferOrderLogService::class, ['filename' => LogConstService::TRANSFER_ORDER_LOG_PREFIX . $order->id]);
        $createTransferOrderLogService->excute($order->id, '第三方代付失败提示', '订单未提交到渠道，系统自动判定失败');
    }

    private function failPendingOrder(int $orderId): bool
    {
        $handled = TransferOrder::where('id', $orderId)->where('status', 2)->update([
            'status' => 3,
            'remark' => '订单未提交到渠道，系统自动判定失败',
        ]) > 0;

        if (!$handled) {
            return false;
        }

        $failedOrder = TransferOrder::query()->find($orderId);
        if ($failedOrder) {
            cache_transfer_info($failedOrder);
            App::make(OrderStatusReportRepairService::class)->forTransferOrder($failedOrder);
        }

        return true;
    }

    private function duplicateLockKey(): string
    {
        return 'job:query_transfer_order_submit_success:' . $this->id;
    }

    private function noticeException(Throwable $e): void
    {
        try {
            app(SystemNoticeService::class)->warning('system_manual_notice', ['message' => '代付提交确认查询异常：' . $e->getMessage(), 'id' => $this->id]);
        } catch (Throwable $noticeException) {
            report($noticeException);
        }
    }
}
