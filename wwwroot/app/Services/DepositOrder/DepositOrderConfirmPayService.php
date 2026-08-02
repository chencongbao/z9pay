<?php

namespace App\Services\DepositOrder;

use Throwable;
use ReflectionMethod;
use RuntimeException;
use App\Models\DepositOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use App\Traits\ServiceResponseTrait;
use App\Services\Enums\ErrorCodeEnum;
use Illuminate\Support\Facades\Cache;
use App\Services\Order\OrderCacheService;
use Illuminate\Database\Eloquent\Builder;
use App\Services\Cache\CacheConstPrefixService;
use App\Services\Common\ReportExceptionService;
use App\Services\Cache\Channel\ChannelInfoByChannelIdService;
use App\Services\DepositOrderLog\CreateDepositOrderLogService;

class DepositOrderConfirmPayService
{
    use ServiceResponseTrait;

    private const ALLOWED_CONFIRM_STATUSES = [1, 3];

    private const CONFIRM_LOCK_SECONDS = 120;

    public function confirmByOrdernumber(string $ordernumber, array $data, array $cachedOrder = []): array
    {
        $order = $cachedOrder ?: App::make(OrderCacheService::class)->getDepositByOrdernumber($ordernumber, true);

        return $this->confirm(function (Builder $query) use ($ordernumber) {
            $query->where('ordernumber', $ordernumber);
        }, $data, $order);
    }

    public function confirmByOrderId(int $orderId, array $data, int $mid = 0, array $cachedOrder = []): array
    {
        $order = $cachedOrder ?: App::make(OrderCacheService::class)->getDepositById($orderId, true);
        if ($mid > 0 && (int) ($order['mid'] ?? 0) !== $mid) {
            $order = [];
        }

        return $this->confirm(function (Builder $query) use ($orderId, $mid) {
            $query->where('id', $orderId);
            if ($mid > 0) {
                $query->where('mid', $mid);
            }
        }, $data, $order);
    }

    private function confirm(callable $queryCallback, array $data, array $cachedOrder = []): array
    {
        $order = null;
        $lock = null;
        $lockAcquired = false;

        try {
            if (empty($cachedOrder)) {
                return $this->orderNotFoundFail();
            }

            if (!in_array((int) ($cachedOrder['status'] ?? 0), self::ALLOWED_CONFIRM_STATUSES, true)) {
                $this->writeOrderLog((int) $cachedOrder['id'], '会员确认付款失败：订单状态不允许', [
                    '订单号' => $cachedOrder['ordernumber'] ?? '',
                    '订单状态' => $cachedOrder['status'] ?? null,
                    '付款状态' => $cachedOrder['pay_status'] ?? null,
                    '允许状态' => self::ALLOWED_CONFIRM_STATUSES,
                    '请求参数' => $data,
                ], 'warning');

                return $this->orderStatusInvalidFail();
            }

            $cachedOrderId = (int) ($cachedOrder['id'] ?? 0);
            if ($cachedOrderId <= 0) {
                return $this->orderNotFoundFail();
            }

            // 先取得带所有权的订单锁，避免并发重复查询和调用第三方确认接口。
            $lock = Cache::lock($this->confirmLockKey($cachedOrderId), self::CONFIRM_LOCK_SECONDS);
            $lockAcquired = $lock->get();
            if (!$lockAcquired) {
                $this->writeOrderLog($cachedOrderId, '会员确认付款失败：订单正在确认中', [
                    '订单号' => $cachedOrder['ordernumber'] ?? '',
                    '订单状态' => $cachedOrder['status'] ?? null,
                    '付款状态' => $cachedOrder['pay_status'] ?? null,
                    '请求参数' => $data,
                ], 'warning');

                return $this->confirmingFail();
            }

            // 首次复核不持有无效的数据库事务，最终状态写入时再加行锁。
            $query = DepositOrder::query();
            $queryCallback($query);
            $order = $query->first($this->confirmFields());
            if (!$order) {
                return $this->orderNotFoundFail();
            }

            if (!in_array((int) $order->status, self::ALLOWED_CONFIRM_STATUSES, true)) {
                $this->writeOrderLog($order->id, '会员确认付款失败：订单状态不允许', [
                    '订单号' => $order->ordernumber,
                    '订单状态' => $order->status,
                    '付款状态' => $order->pay_status,
                    '允许状态' => self::ALLOWED_CONFIRM_STATUSES,
                    '请求参数' => $data,
                ], 'warning');

                return $this->orderStatusInvalidFail();
            }

            $channel = App::make(ChannelInfoByChannelIdService::class)->excute($order->channel_id);
            if (!$channel || empty($channel['classname'])) {
                $this->writeOrderLog($order->id, '会员确认付款失败：渠道配置异常', [
                    '订单号' => $order->ordernumber,
                    'channel_id' => $order->channel_id,
                    '渠道信息' => $channel,
                    '请求参数' => $data,
                ], 'warning');

                return $this->fail(__('cashier.params_error'));
            }

            $classname = 'Richard\\Payment\\Channel\\' . $channel['classname'];
            if (!class_exists($classname)) {
                $e = new RuntimeException('通道类不存在：' . $classname);
                $this->reportException($this->exceptionReportTitle(), $e, [
                    'data' => $data,
                    'ordernumber' => $order->ordernumber ?? '',
                    'order_id' => $order->id ?? 0,
                    'channel_id' => $channel['id'] ?? 0,
                    'channel_name' => $channel['name'] ?? '',
                ]);
                return $this->exceptionFail();
            }
            if (!$this->hasOwnConfirmPay($classname)) {
                $e = new RuntimeException('通道未实现confirmPay方法：' . $classname);
                $this->reportException($this->exceptionReportTitle(), $e, [
                    'data' => $data,
                    'ordernumber' => $order->ordernumber ?? '',
                    'order_id' => $order->id ?? 0,
                    'channel_id' => $channel['id'] ?? 0,
                    'channel_name' => $channel['name'] ?? '',
                ]);
                return $this->exceptionFail();
            }

            $payment = App::make($classname);
            try {
                $confirmResult = $payment->confirmPay($order->id, $data);
            } catch (Throwable $e) {
                $this->writeOrderLog($order->id, '会员确认付款通道请求异常', [
                    '通道名称' => $channel['name'] ?? '',
                    '通道类名' => $classname,
                    '异常类型' => get_class($e),
                    '异常信息' => $e->getMessage(),
                ], 'error');

                $this->reportException($this->exceptionReportTitle(), $e, [
                    'data' => $data,
                    'ordernumber' => $order->ordernumber ?? '',
                    'order_id' => $order->id ?? 0,
                    'channel_id' => $channel['id'] ?? 0,
                    'channel_name' => $channel['name'] ?? '',
                    'channel_classname' => $classname,
                ]);

                return $this->exceptionFail();
            }

            if ($confirmResult !== true) {
                $this->writeOrderLog($order->id, '会员确认付款通道提交失败', [
                    '通道名称' => $channel['name'] ?? '',
                    '通道类名' => $classname,
                    '返回结果' => $confirmResult,
                    '请求参数' => $data,
                ], 'error');

                return $this->fail(
                    $this->exceptionMessage(),
                    $this->exceptionZhMessage(),
                    ErrorCodeEnum::COMMON_ERROR
                );
            }

            $updateResult = $this->markConfirmedAfterChannelSuccess((int) $order->id, $data);
            if (empty($updateResult['success'])) {
                return $updateResult;
            }

            $order = $updateResult['data']['order'];

            return $this->success(['return_url' => $order->return_url], __('cashier.success'));
        } catch (Throwable $e) {
            $this->reportException($this->exceptionReportTitle(), $e, [
                'data' => $data,
                'ordernumber' => $order->ordernumber ?? '',
                'order_id' => $order->id ?? 0,
            ]);

            return $this->exceptionFail();
        } finally {
            if ($lockAcquired && $lock) {
                try {
                    $lock->release();
                } catch (Throwable $e) {
                    $this->reportException('确认付款锁释放失败', $e, [
                        'order_id' => $order->id ?? ($cachedOrder['id'] ?? 0),
                        'ordernumber' => $order->ordernumber ?? ($cachedOrder['ordernumber'] ?? ''),
                    ]);
                }
            }
        }
    }

    private function markConfirmedAfterChannelSuccess(int $orderId, array $data): array
    {
        $result = DB::transaction(function () use ($orderId, $data) {
            $order = DepositOrder::query()
                ->where('id', $orderId)
                ->lockForUpdate()
                ->first($this->confirmFields());

            if (!$order) {
                return $this->orderNotFoundFail();
            }

            if (!in_array((int) $order->status, self::ALLOWED_CONFIRM_STATUSES, true)) {
                $this->writeOrderLog($order->id, '会员确认付款提交成功后状态更新失败：订单状态已变化', [
                    '订单号' => $order->ordernumber,
                    '订单状态' => $order->status,
                    '付款状态' => $order->pay_status,
                    '允许状态' => self::ALLOWED_CONFIRM_STATUSES,
                    '请求参数' => $data,
                ], 'warning');

                return $this->orderStatusInvalidFail();
            }

            $order->fill([
                'pay_status' => 2,
                'confirm_time' => time(),
                'status' => 7,
            ]);
            $order->save();

            return $this->success(['order' => $order]);
        });

        if (empty($result['success'])) {
            return $result;
        }

        $order = $result['data']['order'];

        // 状态提交后再刷新缓存和记录日志，避免非核心失败回滚已确认状态。
        try {
            App::make(OrderCacheService::class)->putDeposit($order, true);
        } catch (Throwable $e) {
            $this->reportException('确认付款订单缓存刷新失败', $e, [
                'order_id' => $order->id,
                'ordernumber' => $order->ordernumber,
            ]);
        }

        $this->writeOrderLog($order->id, '会员确认付款', [
            '订单状态' => $order->status,
            '付款状态' => $order->pay_status,
            '五位尾号' => $data['fiveFigureOrder'] ?? '',
            'UTR' => $data['utr'] ?? '',
        ]);

        return $result;
    }

    private function writeOrderLog(int $orderId, string $message, array $content = [], string $type = 'info'): void
    {
        try {
            App::make(CreateDepositOrderLogService::class)->excute($orderId, $message, $content, $type);
        } catch (Throwable $e) {
            $this->reportException('确认付款订单日志写入失败', $e, [
                'order_id' => $orderId,
                'message' => $message,
                'type' => $type,
            ]);
        }
    }

    private function reportException(string $title, Throwable $e, array $context = []): void
    {
        try {
            App::make(ReportExceptionService::class)->report($title, $e, $context);
        } catch (Throwable $reportException) {
            try {
                report($reportException);
            } catch (Throwable) {
            }
        }
    }

    private function orderNotFoundFail(): array
    {
        return $this->fail(__('cashier.order_does_not_exist'), '', ErrorCodeEnum::SUBMIT_ORDER_NOT_FOUND);
    }

    private function orderStatusInvalidFail(): array
    {
        return $this->fail(trans('api.order_status_invalid'), '订单状态不允许操作', ErrorCodeEnum::COMMON_ERROR);
    }

    private function confirmingFail(): array
    {
        return $this->fail(trans('api.order_status_invalid'), '订单正在确认中，请稍后再试', ErrorCodeEnum::COMMON_ERROR);
    }

    private function exceptionFail(): array
    {
        return $this->fail(
            $this->exceptionMessage(),
            $this->exceptionZhMessage(),
            ErrorCodeEnum::COMMON_ERROR
        );
    }

    private function exceptionReportTitle(): string
    {
        return '确认付款通道确认失败';
    }

    private function exceptionMessage(): string
    {
        return trans('api.submit_failed_contact_kefu');
    }

    private function exceptionZhMessage(): string
    {
        return '提交失败，请联系客服';
    }

    private function hasOwnConfirmPay(string $classname): bool
    {
        try {
            $method = new ReflectionMethod($classname, 'confirmPay');
            return $method->getDeclaringClass()->getName() === $classname;
        } catch (Throwable) {
            return false;
        }
    }

    private function confirmFields(): array
    {
        return array_values(array_unique(array_merge(CacheConstPrefixService::CACHE_DEPOSIT_FILED, ['id', 'status', 'channel_id'])));
    }

    private function confirmLockKey(int $orderId): string
    {
        return 'deposit_order_confirm_pay_lock_' . $orderId;
    }
}
