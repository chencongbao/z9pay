<?php

namespace App\Jobs;

use Throwable;
use App\Models\Channel;
use App\Models\TransferOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
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
use App\Services\TransferOrder\TransferOrderSuccessService;
use App\Services\SettlementOrder\SettlementOrderSuccessService;
use App\Services\TransferOrderLog\CreateTransferOrderLogService;

class QueryAlipaybeifujinTransferOrderStatusAndHandleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const DUPLICATE_LOCK_SECONDS = 4;

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

        $createTransferOrderLogService = App::makeWith(CreateTransferOrderLogService::class, ['filename' => LogConstService::TRANSFER_ORDER_LOG_PREFIX . $order->id]);
        $createTransferOrderLogService->excute($order->id, '代付订单状态开始查询', []);

        $channel = Channel::find($order->channel_id, ['id', 'classname']);
        $classname = 'Richard\\Payment\\Channel\\' . optional($channel)->classname;
        if (empty(optional($channel)->classname) || !class_exists($classname)) {
            throw new \Exception('北富金代付查询渠道类不存在，订单ID：' . $order->id);
        }

        $payment = new $classname(LogConstService::TRANSFER_ORDER_LOG_PREFIX . $order->id);
        $result = $payment->queryTransferStatus($order->ordernumber);
        if (!empty($result) && ($result['orderStatus'] ?? 0) == 2) {
            dispatch(new QueryAlipaybeifujinTransferOrderStatusAndHandleJob($order->id))->delay(now()->addSecond(5))->onQueue('query');
            return;
        }

        DB::transaction(function () use ($order, $result, $payment, $createTransferOrderLogService) {
            $lockedOrder = TransferOrder::where('id', $order->id)->where('status', 2)->lockForUpdate()->first();
            if (!$lockedOrder) {
                return;
            }

            if (!empty($result) && ($result['orderStatus'] ?? 0) == 1) {
                $this->handleSuccess($lockedOrder, $result);
                $createTransferOrderLogService->excute($lockedOrder->id, '第三方代付成功查询订单信息', $result);
                return;
            }

            $this->handleFailOrPending($lockedOrder, $payment->error ?: '第三方代付失败');
            $createTransferOrderLogService->excute($lockedOrder->id, '第三方代付失败提示', $payment->error);
        });
    }

    private function handleSuccess(TransferOrder $order, array $result): void
    {
        if ($order->type == 0) {
            App::makeWith(TransferOrderSuccessService::class, ['filename' => LogConstService::TRANSFER_ORDER_LOG_PREFIX . $order->id])->excute($order->id, floatval($result['callback_order_amount'] ?? 0));
        }
        if ($order->type == 1) {
            App::makeWith(SettlementOrderSuccessService::class, ['filename' => LogConstService::TRANSFER_ORDER_LOG_PREFIX . $order->id])->excute($order->id, floatval($result['callback_order_amount'] ?? 0));
        }
    }

    private function handleFailOrPending(TransferOrder $order, string $remark): void
    {
        if ($order->type == 0) {
            if (intval(config('other.transfer_pending_status', 1)) == 1) {
                $order->status = 3;
                $order->remark = $remark;
                $order->save();
                bob_send_system_transfer_notice(['success_text' => '代付订单处理中，订单号：' . $order->ordernumber, 'voice_id' => 'transfer_6', 'id' => 6]);
                cache_transfer_info($order);
                App::make(OrderStatusReportRepairService::class)->forTransferOrder($order);
                return;
            }

            App::makeWith(TransferOrderFailService::class, ['filename' => LogConstService::TRANSFER_ORDER_LOG_PREFIX . $order->id])->excute($order->id, $remark);
            return;
        }

        if ($order->type == 1) {
            $order->status = 3;
            $order->remark = $remark;
            $order->save();
            cache_transfer_info($order);
            App::make(OrderStatusReportRepairService::class)->forTransferOrder($order);
        }
    }

    private function duplicateLockKey(): string
    {
        return 'job:query_alipaybeifujin_transfer_order_status:' . $this->id;
    }

    private function noticeException(Throwable $e): void
    {
        try {
            app(SystemNoticeService::class)->warning('system_manual_notice', ['message' => '北富金代付查询异常：' . $e->getMessage(), 'id' => $this->id]);
        } catch (Throwable $noticeException) {
            report($noticeException);
        }
    }
}
