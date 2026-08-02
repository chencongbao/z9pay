<?php

namespace App\Jobs;

use Exception;
use Throwable;
use App\Models\Channel;
use App\Models\TransferOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Queue\SerializesModels;
use App\Services\Const\LogConstService;
use Illuminate\Queue\InteractsWithQueue;
use App\Services\Order\OrderCacheService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use App\Services\Cache\CacheConstPrefixService;
use App\Services\Common\ReportExceptionService;
use App\Services\TransferOrder\TransferOrderFailService;
use App\Services\TransferOrderLog\CreateTransferOrderLogService;

class SendTransferOrderPaymentJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $order;

    public $info;

    public $tries = 1;

    public $timeout = 1000;

    private $runtimeLogService = null;

    private ?array $channelLogContext = null;

    public function __construct(TransferOrder $order, $info = [])
    {
        $this->order = $order;
        $this->info = $info;
    }

    // 同一订单只允许一个代付下发 Job 在队列里。
    public function uniqueId(): string
    {
        return 'transfer_submit_' . $this->order->id;
    }

    // 唯一锁覆盖第三方最大处理时间。
    public function uniqueFor(): int
    {
        return 300;
    }

    public function handle()
    {
        try {
            // 校验订单是否仍允许下发。
            if (!$this->shouldSubmitOrder()) {
                return;
            }

            // 初始化渠道类，配置错误需要通知技术。
            $filename = LogConstService::TRANSFER_ORDER_LOG_PREFIX . $this->order->id;
            try {
                $className = $this->channelClassName();
                $channel = new $className($filename);
            } catch (Throwable $e) {
                App::make(ReportExceptionService::class)->report('代付下发通道配置异常', $e, [
                    'order_id' => $this->order->id,
                    'ordernumber' => $this->order->ordernumber ?? '',
                    'channel_info' => $this->info,
                ]);
                $this->failOrder($e->getMessage() ?: '代付通道配置异常');
                return;
            }

            // 请求渠道代付。
            try {
                $ret = $channel->transfer($this->order->id);
                if (!empty($ret)) {
                    $this->successOrder($ret);
                    return;
                }
                $this->failOrder($channel->error ?: '渠道返回失败', $channel->balance_insufficient);
            } catch (Exception $e) {
                $this->failOrder($e->getMessage() ?: '渠道返回失败');
            } catch (Throwable $e) {
                App::make(ReportExceptionService::class)->report('代付下发代码异常', $e, [
                    'order_id' => $this->order->id,
                    'ordernumber' => $this->order->ordernumber ?? '',
                    'channel_info' => $this->info,
                ]);
                $this->failOrder($e->getMessage() ?: '代付下发代码异常');
            }
        } finally {
            $this->clearCacheHandle();
        }
    }

    public function clearCacheHandle()
    {
        $cacheKey = CacheConstPrefixService::HANDLE_DO_TRANSFER_ACTION . $this->order->id;
        Cache::forget($cacheKey);
    }

    private function shouldSubmitOrder(): bool
    {
        try {
            return DB::transaction(function () {
                $order = TransferOrder::where('id', $this->order->id)->lockForUpdate()->first(['id', 'status', 'ordernumber', 'order_no']);
                if (!$order) {
                    return false;
                }

                if (!in_array((int)$order->status, [1, 3], true)) {
                    $this->writeLog($this->order->id, '代付下发跳过', ['current_status' => $order->status, 'reason' => '当前状态不允许代付'], 'debug');
                    return false;
                }

                $this->writeLog($this->order->id, '代付下发开始', [
                    '系统订单号' => $order->ordernumber,
                    '商户订单号' => $order->order_no,
                    '当前状态' => $order->status,
                ] + $this->channelLogContext(), 'debug');

                return true;
            });
        } catch (Throwable $e) {
            $this->writeLog($this->order->id, '代付下发占位异常', ['message' => $e->getMessage()], 'error');
            return false;
        }
    }

    private function successOrder($ret)
    {
        $updatedOrder = null;

        // 渠道返回成功后更新订单状态。
        DB::transaction(function () use ($ret, &$updatedOrder) {
            $order = TransferOrder::where('id', $this->order->id)->lockForUpdate()->first(['id', 'status', 'remark']);
            if (!$order) {
                return;
            }

            if ((int)$order->status === 2) {
                return;
            }
            $update = $ret;
            $update['status'] = 2;
            $update['remark'] = $this->keepSubmitOperatorRemark($order->remark);
            $order->fill($update);
            $order->save();
            $updatedOrder = $order;
        });

        if ($updatedOrder) {
            $this->refreshOrderCache($updatedOrder);
            $this->writeLog($updatedOrder->id, '代付下发成功', [
                '渠道返回' => $ret,
            ] + $this->channelLogContext(), 'info');
        }
    }

    private function keepSubmitOperatorRemark($remark): string
    {
        $remark = trim((string)$remark);
        if (str_starts_with($remark, '手动代付操作人：') || str_starts_with($remark, '批量代付操作人：')) {
            return $remark;
        }

        return '';
    }

    private function failOrder($reason, $balance_insufficient = 0)
    {
        $this->writeLog($this->order->id, '代付下发失败', [
            '失败原因' => $reason,
            '是否渠道余额不足' => intval($balance_insufficient) === 1 ? '是' : '否',
        ] + $this->channelLogContext(), 'error');
        $pendingStatusEnabled = intval(bob_admin_setting('other_transfer_pending_status')) == 1;
        $balancePendingEnabled = intval(bob_admin_setting('transfer_balance_insufficient_status')) == 1;
        $pendingOrder = null;

        // 渠道失败后按配置决定进入待处理或直接失败。
        DB::transaction(function () use ($reason, $balance_insufficient, $pendingStatusEnabled, $balancePendingEnabled, &$pendingOrder) {
            $order = TransferOrder::where('id', $this->order->id)->lockForUpdate()->first(['id', 'status', 'ordernumber']);
            if (!$order) {
                return;
            }
            if ((int)$order->status === 2) {
                return;
            }
            if ($balance_insufficient == 1 && $balancePendingEnabled) {
                $order->fill(['status' => 3, 'remark' => $reason]);
                $order->save();
                $pendingOrder = $order;
            } elseif ($pendingStatusEnabled) {
                $order->fill(['status' => 3, 'remark' => $reason]);
                $order->save();
                $pendingOrder = $order;
            } else {
                App::make(TransferOrderFailService::class)->excute($this->order->id, $reason);
            }
        });
        if ($pendingOrder) {
            $this->writeLog($pendingOrder->id, '代付订单进入待处理', [
                '原因' => $reason,
                '是否渠道余额不足' => intval($balance_insufficient) === 1 ? '是' : '否',
                '失败进入待处理开关' => $pendingStatusEnabled ? '开启' : '关闭',
                '余额不足进入待处理开关' => $balancePendingEnabled ? '开启' : '关闭',
            ], 'error');
            $this->refreshOrderCache($pendingOrder);
            bob_send_system_transfer_notice(['success_text' => '代付订单待处理，订单号：' . $pendingOrder->ordernumber, 'voice_id' => 'transfer_3', 'id' => 3]);
        }
    }

    private function channelClassName(): string
    {
        $classname = trim((string)($this->info['classname'] ?? ''));
        if ($classname === '') {
            throw new Exception('代付通道类名为空');
        }

        $className = 'Richard\\Payment\\Channel\\' . $classname;
        if (!class_exists($className)) {
            throw new Exception('代付通道类不存在：' . $classname);
        }

        return $className;
    }

    private function channelLogContext(): array
    {
        if ($this->channelLogContext !== null) {
            return $this->channelLogContext;
        }

        $channelId = $this->info['channel_id'] ?? $this->info['id'] ?? '';
        $channelName = $this->info['channel_name'] ?? $this->info['name'] ?? '';

        if (($channelId === '' || $channelName === '') && !empty($this->info['classname'])) {
            $channel = Channel::query()
                ->where('classname', $this->info['classname'])
                ->first(['id', 'name']);

            if ($channel) {
                $channelId = $channelId ?: $channel->id;
                $channelName = $channelName ?: $channel->name;
            }
        }

        return $this->channelLogContext = [
            '渠道ID' => $channelId ?: '',
            '渠道名称' => $channelName ?: '未知渠道',
        ];
    }

    private function writeLog($orderId, string $title, $content = '', string $type = 'info'): void
    {
        try {
            $this->runtimeLogService()->excute($orderId, $title, $content, $type);
        } catch (Throwable $e) {
            App::make(ReportExceptionService::class)->report('代付订单日志写入失败', $e, [
                'order_id' => $orderId,
                'title' => $title,
                'content' => $content,
                'type' => $type,
            ]);
        }
    }

    private function runtimeLogService()
    {
        if ($this->runtimeLogService) {
            return $this->runtimeLogService;
        }

        $this->runtimeLogService = App::make(CreateTransferOrderLogService::class);

        return $this->runtimeLogService;
    }

    private function refreshOrderCache(TransferOrder $order): void
    {
        App::make(OrderCacheService::class)->putTransfer($order, true);
    }
}
