<?php

namespace App\Jobs;

use Throwable;
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
use App\Services\TransferOrder\TransferOrderSuccessService;
use App\Services\SettlementOrder\SettlementOrderSuccessService;

class TimingQueryTransferOrderResultJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const DUPLICATE_LOCK_SECONDS = 30;

    public $id;

    public $timeout = 120;

    public $tries = 1;

    public function __construct($id = 0)
    {
        $this->id = $id;
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
        $model = TransferOrder::where('id', $this->id)->where('status', 2)->with(['channel' => function ($query) {
            $query->select('id', 'classname');
        }])->first(['id', 'ordernumber', 'channel_id', 'type']);
        if (!$model) {
            return;
        }

        $classname = 'Richard\\Payment\\Channel\\' . optional($model->channel)->classname;
        if (empty(optional($model->channel)->classname) || !class_exists($classname)) {
            throw new \Exception('代付定时查询渠道类不存在，订单ID：' . $model->id);
        }

        $payment = new $classname(LogConstService::TRANSFER_ORDER_LOG_PREFIX . $model->id);
        $result = $payment->queryTransferStatus($model->ordernumber);
        if (empty($result) || !isset($result['callback_order_status'])) {
            return;
        }

        if ($result['callback_order_status'] == 1) {
            if ($model->type == 0) {
                $transferOrderSuccessService = App::makeWith(TransferOrderSuccessService::class, ['filename' => LogConstService::TRANSFER_ORDER_LOG_PREFIX . $model->id]);
                $transferOrderSuccessService->excute($model->id, floatval($result['callback_order_amount'] ?? 0));
            }
            if ($model->type == 1) {
                $settlementOrderSuccessService = App::makeWith(SettlementOrderSuccessService::class, ['filename' => LogConstService::TRANSFER_ORDER_LOG_PREFIX . $model->id]);
                $settlementOrderSuccessService->excute($model->id, floatval($result['callback_order_amount'] ?? 0));
            }
        }
        if ($result['callback_order_status'] == 3) {
            if ($model->type == 0) {
                if (intval(config('other.transfer_pending_status', 1)) == 1) {
                    $this->failOrderAsPending($model->id, $payment->error ?: '第三方代付失败');
                } else {
                    App::makeWith(TransferOrderFailService::class, ['filename' => LogConstService::TRANSFER_ORDER_LOG_PREFIX . $model->id])->excute($model->id, $payment->error ?: '第三方代付失败');
                }
            }
            if ($model->type == 1) {
                $this->failOrderAsPending($model->id, $payment->error ?: '第三方代付失败');
            }
        }
    }

    private function failOrderAsPending(int $orderId, string $remark): void
    {
        $handled = TransferOrder::where('id', $orderId)->where('status', 2)->update([
            'status' => 3,
            'remark' => $remark,
        ]) > 0;

        if (!$handled) {
            return;
        }

        $failedOrder = TransferOrder::query()->find($orderId);
        if (!$failedOrder) {
            return;
        }

        cache_transfer_info($failedOrder);
        App::make(OrderStatusReportRepairService::class)->forTransferOrder($failedOrder);
    }

    private function duplicateLockKey(): string
    {
        return 'job:timing_query_transfer_order_result:' . $this->id;
    }

    private function noticeException(Throwable $e): void
    {
        try {
            app(SystemNoticeService::class)->warning('system_manual_notice', ['message' => '代付定时查询异常：' . $e->getMessage(), 'id' => $this->id]);
        } catch (Throwable $noticeException) {
            report($noticeException);
        }
    }
}
