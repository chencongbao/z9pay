<?php

namespace App\Jobs;

use Throwable;
use App\Models\TransferOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Cache;
use App\Services\Report\OrderStatusReportRepairService;
use Illuminate\Queue\SerializesModels;
use App\Services\Const\LogConstService;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use App\Services\SystemNotice\SystemNoticeService;
use App\Services\TransferOrder\TransferOrderFailService;
use App\Services\TransferOrder\TransferOrderSuccessService;
use App\Services\SettlementOrder\SettlementOrderSuccessService;

class HandlePendingQueryTransferOrderResultJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const DUPLICATE_LOCK_SECONDS = 120;

    public $id;

    public $tries = 1;

    public $timeout = 120;

    public function __construct($id = 0)
    {
        $this->id = intval($id);
    }

    public function uniqueFor(): int
    {
        return self::DUPLICATE_LOCK_SECONDS;
    }

    public function uniqueId()
    {
        return 'HandlePendingQueryTransferOrderResultJob_' . $this->id;
    }

    public function handle()
    {
        try {
            if ($this->id <= 0) {
                return;
            }

            // ShouldBeUnique 防并发重复入队；这里再做执行冷却，避免短时间重复请求渠道。
            if (!Cache::add($this->duplicateLockKey(), 1, now()->addSeconds(self::DUPLICATE_LOCK_SECONDS))) {
                return;
            }

            $model = TransferOrder::query()
                ->whereKey($this->id)
                ->where('status', 2)
                ->with(['channel' => function ($query) {
                    $query->select('id', 'classname');
                }])
                ->first(['id', 'ordernumber', 'channel_id', 'type']);
            if (!$model) {
                return;
            }

            $channelClassname = optional($model->channel)->classname;
            $classname = 'Richard\\Payment\\Channel\\' . $channelClassname;
            if (empty($channelClassname) || !class_exists($classname)) {
                throw new \Exception('代付查询渠道类不存在，渠道ID：' . $model->channel_id);
            }

            $filename = LogConstService::TRANSFER_ORDER_LOG_PREFIX . $model->id;
            $payment = new $classname($filename);
            $result = $payment->queryTransferOrderResult($model->ordernumber);
            if (empty($result)) {
                return;
            }

            if (!isset($result['callback_order_status'])) {
                throw new \Exception('代付查询返回缺少 callback_order_status');
            }

            if ((int)$result['callback_order_status'] === 1) {
                if (!isset($result['callback_order_amount'])) {
                    throw new \Exception('代付查询成功返回缺少 callback_order_amount');
                }
                $this->handleSuccess($model, floatval($result['callback_order_amount']));
            }

            if ((int)$result['callback_order_status'] === 3) {
                $this->handleFailed($model, $payment->error ?: '第三方代付失败');
            }
        } catch (Throwable $e) {
            app(SystemNoticeService::class)->warning('system_manual_notice', [
                'error' => $e->getMessage(),
                'info' => '查询代付订单异常',
                'id' => $this->id,
            ]);
        }
    }

    private function handleSuccess(TransferOrder $model, float $amount): void
    {
        if ((int)$model->type === 0) {
            app(TransferOrderSuccessService::class)->excute($model->id, $amount);
        }

        if ((int)$model->type === 1) {
            app(SettlementOrderSuccessService::class)->excute($model->id, $amount);
        }
    }

    private function handleFailed(TransferOrder $model, string $remark): void
    {
        if ((int)$model->type === 0) {
            if (intval(bob_admin_setting('other_transfer_pending_status')) === 1) {
                $model->status = 3;
                $model->remark = $remark;
                $model->save();
                cache_transfer_info($model);
                App::make(OrderStatusReportRepairService::class)->forTransferOrder($model);
                return;
            }

            app(TransferOrderFailService::class)->excute($model->id, $remark);
        }

        if ((int)$model->type === 1) {
            $model->status = 3;
            $model->remark = $remark;
            $model->save();
            cache_transfer_info($model);
            App::make(OrderStatusReportRepairService::class)->forTransferOrder($model);
        }
    }

    private function duplicateLockKey(): string
    {
        return 'transfer_order:pending_query:lock:' . $this->id;
    }
}
