<?php

namespace App\Services\DepositOrder;

use Throwable;
use RuntimeException;
use App\Models\FreezeOrder;
use App\Models\DepositOrder;
use Illuminate\Support\Facades\DB;
use App\Services\Order\OrderCacheService;
use App\Services\SystemNotice\SystemNoticeService;
use App\Services\Merchant\MerchantBalanceChangeService;

class DepositOrderFreezeService
{
    public function excute($order_id = 0, $amount = 0, $remark = '', int $adminId = 0): FreezeOrder
    {
        try {
            $orderId = intval($order_id);
            $remark = trim((string) $remark);
            $freezeAmount = bob_amount_format($amount);

            if ($orderId <= 0) {
                throw new RuntimeException('订单不存在或当前状态不可冻结');
            }

            if ($freezeAmount <= 0) {
                throw new RuntimeException('冻结金额必须大于0');
            }

            $freezeOrder = DB::transaction(function () use ($orderId, $freezeAmount, $remark, $adminId) {
                $order = DepositOrder::whereKey($orderId)
                    ->where('status', 5)
                    ->lockForUpdate()
                    ->first([
                        'id',
                        'status',
                        'mid',
                        'user_id',
                        'amount',
                        'actual_amount',
                        'freeze_amount',
                        'currency_id',
                        'payment_id',
                        'ordernumber',
                        'order_no',
                    ]);

                if (!$order) {
                    throw new RuntimeException('订单不存在或当前状态不可冻结');
                }

                $maxAmount = bob_amount_format(floatval($order->actual_amount) > 0 ? $order->actual_amount : $order->amount);
                if ($this->amountGreaterThan($freezeAmount, $maxAmount)) {
                    throw new RuntimeException('冻结金额不能大于订单实付金额');
                }

                $frozenAmount = bob_amount_format(FreezeOrder::where('deposit_order_id', $order->id)->where('status', 1)->sum('amount'));
                $remainingFreezeAmount = max(0, bob_amount_format($maxAmount - $frozenAmount));
                if ($this->amountGreaterThan($freezeAmount, $remainingFreezeAmount)) {
                    throw new RuntimeException('冻结金额不能大于订单剩余可冻结金额：' . bob_unit_format($remainingFreezeAmount));
                }

                $order->freeze_amount = bob_amount_format($frozenAmount + $freezeAmount);
                $order->save();

                $freezeOrder = FreezeOrder::create([
                    'mid' => $order->mid,
                    'user_id' => $order->user_id,
                    'amount' => $freezeAmount,
                    'deposit_order_id' => $order->id,
                    'status' => 1,
                    'remark' => $remark,
                ]);

                // 冻结商户余额并写入余额流水。
                $result = app(MerchantBalanceChangeService::class)->excute([
                    'mid' => $order->mid,
                    'amount' => -$freezeAmount,
                    'fee' => 0,
                    'type' => 9,
                    'admin_id' => $adminId,
                    'type_id' => $order->id,
                    'currency_id' => $order->currency_id,
                    'payment_id' => $order->payment_id,
                    'remark' => $remark,
                    'order_type' => 1,
                    'ordernumber' => $order->ordernumber,
                    'order_no' => $order->order_no,
                ]);

                if (empty($result['success'])) {
                    throw new RuntimeException($result['message'] ?? '商户余额冻结失败');
                }

                return $freezeOrder->setRelation('deposit_order', $order);
            });

            $this->refreshOrderCache($freezeOrder, '订单冻结缓存刷新异常');

            return $freezeOrder;
        } catch (Throwable $e) {
            $this->noticeWarning('订单冻结执行异常', $e, [
                'order_id' => $orderId ?? $order_id,
                'freeze_amount' => $freezeAmount ?? $amount,
                'admin_id' => $adminId,
                'remark' => $remark,
            ]);
            throw $e;
        }
    }

    public function unfreeze($freezeOrderId = 0, string $remark = '', int $adminId = 0): FreezeOrder
    {
        try {
            $freezeOrderId = intval($freezeOrderId);
            $remark = trim($remark);

            if ($freezeOrderId <= 0) {
                throw new RuntimeException('冻结订单不存在或已解冻');
            }

            $freezeOrder = DB::transaction(function () use ($freezeOrderId, $remark, $adminId) {
                $freezeOrder = FreezeOrder::whereKey($freezeOrderId)
                    ->where('status', 1)
                    ->lockForUpdate()
                    ->first(['id', 'mid', 'user_id', 'amount', 'deposit_order_id', 'status', 'remark', 'unfreeze_time']);

                if (!$freezeOrder) {
                    throw new RuntimeException('冻结订单不存在或已解冻');
                }

                $order = DepositOrder::whereKey($freezeOrder->deposit_order_id)
                    ->lockForUpdate()
                    ->first([
                        'id',
                        'mid',
                        'amount',
                        'actual_amount',
                        'freeze_amount',
                        'currency_id',
                        'payment_id',
                        'ordernumber',
                        'order_no',
                    ]);

                if (!$order) {
                    throw new RuntimeException('代收订单不存在');
                }

                $freezeOrder->unfreeze_time = time();
                $freezeOrder->status = 0;
                $freezeOrder->remark = $remark;
                $freezeOrder->save();

                $order->freeze_amount = max(0, bob_amount_format(floatval($order->freeze_amount) - floatval($freezeOrder->amount)));
                $order->save();

                // 解冻商户余额并写入余额流水。
                $result = app(MerchantBalanceChangeService::class)->excute([
                    'mid' => $freezeOrder->mid,
                    'amount' => $freezeOrder->amount,
                    'fee' => 0,
                    'type' => 10,
                    'admin_id' => $adminId,
                    'type_id' => $freezeOrder->id,
                    'currency_id' => $order->currency_id,
                    'payment_id' => $order->payment_id,
                    'remark' => $remark,
                    'order_type' => 3,
                    'ordernumber' => $order->ordernumber,
                    'order_no' => $order->order_no,
                ]);

                if (empty($result['success'])) {
                    throw new RuntimeException($result['message'] ?? '商户余额解冻失败');
                }

                return $freezeOrder->setRelation('deposit_order', $order);
            });

            $this->refreshOrderCache($freezeOrder, '订单解冻缓存刷新异常');

            return $freezeOrder;
        } catch (Throwable $e) {
            $this->noticeWarning('订单解冻执行异常', $e, [
                'freeze_order_id' => $freezeOrderId,
                'admin_id' => $adminId,
                'remark' => $remark,
            ]);
            throw $e;
        }
    }

    private function amountGreaterThan($left, $right): bool
    {
        if (function_exists('bccomp')) {
            return bccomp((string) $left, (string) $right, 2) > 0;
        }

        return (float) $left > (float) $right;
    }

    private function refreshOrderCache(FreezeOrder $freezeOrder, string $action): void
    {
        $order = $freezeOrder->getRelation('deposit_order');
        if (!$order instanceof DepositOrder) {
            return;
        }

        try {
            app(OrderCacheService::class)->putDeposit($order, true);
        } catch (Throwable $e) {
            $this->noticeWarning($action, $e, [
                'order_id' => $order->id,
                'freeze_order_id' => $freezeOrder->id,
            ]);
        }
    }

    private function noticeWarning(string $action, Throwable $e, array $context = []): void
    {
        try {
            app(SystemNoticeService::class)->warning('deposit_order_freeze_exception', array_merge([
                'action' => $action,
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], $context));
        } catch (Throwable) {
            // 告警失败不能覆盖原始资金异常或已提交的冻结结果。
        }
    }
}
