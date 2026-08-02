<?php

namespace App\Jobs;

use Throwable;
use App\Models\TransferOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Queue\SerializesModels;
use App\Services\Const\LogConstService;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\Cache\CacheConstPrefixService;
use App\Services\Common\ReportExceptionService;
use App\Services\TransferOrder\TransferOrderFailService;
use App\Services\TransferOrderLog\CreateTransferOrderLogService;

class SendTransferOrderTelegramConfirmCancelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $order_id;

    public $message;

    public function __construct($order_id, $message)
    {
        $this->order_id = $order_id;
        $this->message = $message;
    }

    public function handle(): void
    {
        $order = null;
        $createTransferOrderLogService = null;

        try {
            DB::beginTransaction();

            $order = TransferOrder::where('id', $this->order_id)->lockForUpdate()->first(['id', 'ordernumber', 'status', 'remark', 'channel_info', 'transfer_order_confirm_remark']);
            $filename = LogConstService::TRANSFER_ORDER_LOG_PREFIX . $this->order_id;
            $createTransferOrderLogService = App::makeWith(CreateTransferOrderLogService::class, ['filename' => $filename]);
            if (!$order) {
                throw new \Exception('订单不存在');
            }
            if ((int)$order->status !== 3) {
                throw new \Exception('当前订单状态，无法操作');
            }
            if (!$this->isPendingConfirmOrder($order)) {
                throw new \Exception('当前订单不是待商户群确认状态');
            }
            $channel_info = $order->channel_info ?: [];
            $operatorName = $this->operatorName();
            $order->transfer_order_confirm_remark = [
                "remark" => "确认人：" . $operatorName . "，代付取消",
                "time" => date("Y-m-d H:i:s"),
                "operator_id" => $this->message['from']['id'] ?? 0,
                "operator_name" => $operatorName,
                "action" => "cancel",
            ];
            $order->save();
            $createTransferOrderLogService->excute($order->id, '商户群取消代付', [
                '确认人' => $operatorName,
                '确认动作' => '取消代付',
                '通道ID' => $channel_info['channel_id'] ?? '',
                '通道名称' => $channel_info['name'] ?? '',
            ]);
            App::makeWith(TransferOrderFailService::class, ['filename' => LogConstService::TRANSFER_ORDER_LOG_PREFIX . $order->id])->excute($order->id, "商户取消代付，确认人：" . $operatorName);
            DB::commit();
            $this->releaseConfirmActionLock();
        } catch (Throwable $e) {
            DB::rollBack();
            $this->releaseConfirmActionLock();
            if ($createTransferOrderLogService && $order) {
                $createTransferOrderLogService->excute($order->id, '商户群取消代付失败', [
                    '失败原因' => $e->getMessage(),
                    '当前状态' => $order->status ?? '',
                    '通道信息' => $order->channel_info ?? [],
                    '确认人' => $this->operatorName(),
                ], 'error');
            }
            App::make(ReportExceptionService::class)->report('商户群取消代付失败', $e, [
                'order_id' => $this->order_id,
                'ordernumber' => $order->ordernumber ?? '',
                'status' => $order->status ?? '',
                'channel_info' => $order->channel_info ?? [],
            ]);
        }
    }

    private function operatorName(): string
    {
        $name = trim(($this->message['from']['first_name'] ?? '') . ($this->message['from']['last_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        return (string)($this->message['from']['username'] ?? ($this->message['from']['id'] ?? ''));
    }

    private function releaseConfirmActionLock(): void
    {
        Cache::forget(CacheConstPrefixService::TRANSFER_ORDER_CONFIRM_ACTION . $this->order_id);
    }

    private function isPendingConfirmOrder(TransferOrder $order): bool
    {
        $confirmRemark = $order->transfer_order_confirm_remark ?: [];
        if (($confirmRemark['action'] ?? '') === 'pending_confirm') {
            return true;
        }

        return in_array($order->remark, ['自动代付金额超过免审额度发给商家确认', '商户未绑定群主，未发送确认群消息'], true);
    }
}
