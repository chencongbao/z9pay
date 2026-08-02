<?php

namespace App\Services\DepositOrder\ChannelMode;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use App\Services\Const\LogConstService;
use App\Services\Order\OrderCacheService;
use App\Services\User\ReserveUserDepositOrderService;
use App\Services\SelfNewPayment\GetUserBankSameAmountTimeService;
use App\Services\DepositOrder\DepositOrderPayAmountService;
use App\Services\MerchantPayment\ApplyDepositChannelRateService;

abstract class AbstractChannelModeService implements ChannelModeInterface
{
    protected $error = null;

    public function getError()
    {
        if (is_array($this->error)) return json_encode($this->error);
        return $this->error;
    }

    abstract protected function candidates($order, array $channels, $logService): array;

    abstract protected function modeText(): string;

    protected function needTouchAfter(): bool
    {
        return false;
    }

    /**
     * 是否需要实名
     */
    protected function needRealName($order, array $channels): bool
    {
        return false;
    }

    protected function hasPayName($order): bool
    {
        return !empty($order->payer_name ?? $order->pay_name ?? null);
    }

    protected function channelNeedRealName(array $channel): bool
    {
        return (int)($channel['is_real_name'] ?? 0) === 1;
    }

    protected function anyChannelNeedRealName(array $channels): bool
    {
        foreach ($channels as $channel) {
            if ($this->channelNeedRealName($channel)) {
                return true;
            }
        }

        return false;
    }

    protected function channelLogData(array $channel, $order, array $extra = []): array
    {
        return Arr::collapse([[
            '渠道ID' => $channel['channel_id'] ?? null,
            '渠道名称' => $channel['name'] ?? null,
            '是否实名渠道' => (int)($channel['is_real_name'] ?? 0) === 1 ? '是' : '否',
            '是否已填写付款人姓名' => empty($order->pay_name) ? '否' : '是',
            '优先级' => $channel['priority'] ?? null,
            '权重' => $channel['weight'] ?? null,
            '商户通道ID' => $channel['merchant_channel_id'] ?? null,
        ], $extra]);
    }

    protected function channelListLogData(array $channels, $order): array
    {
        return collect($channels)->filter(fn($channel) => is_array($channel))->map(function ($channel) use ($order) {
            return $this->channelLogData($channel, $order);
        })->values()->all();
    }


    protected function callChannel($order, array $ch, $logService): ?array
    {
        if (intval($ch['channel_id'] ?? 0) !== 1) {
            return $this->callChannelUnlocked($order, $ch, $logService);
        }

        $lockKey = $this->selfChannelDispatchLockKey();
        try {
            return Cache::lock($lockKey, 15)->block(5, function () use ($order, $ch, $logService) {
                return $this->callChannelUnlocked($order, $ch, $logService);
            });
        } catch (\Throwable $e) {
            $this->error = '自营派单繁忙，请稍后重试';
            $logService->excute($order->id, $this->modeText() . '，自营派单锁获取失败', [
                '异常信息' => $e->getMessage(),
                'payment_id' => $order->payment_id,
            ], 'error');

            return null;
        }
    }

    protected function selfChannelDispatchLockKey(): string
    {
        // 金主保证金和待付款限制跨支付方式共享，必须统一串行完成选卡和最终预占。
        return 'self_channel_deposit_dispatch';
    }

    private function callChannelUnlocked($order, array $ch, $logService): ?array
    {
        $order = App::make(DepositOrderPayAmountService::class)->applyByChannel($order, $ch, $logService);

        try {
            if (!$this->applyFinalRateAndChannel($order, $ch, $logService)) {
                return null;
            }

            $classname = 'Richard\\Payment\\Channel\\' . $ch['classname'];
            if (!class_exists($classname)) {
                $this->error = "通道类不存在:{$classname}";
                $logService->excute($order->id, $this->modeText() . "，请求渠道【" . ($ch['name'] ?? null) . "】, 请求失败", $this->error, 'error');
                return null;
            }

            if (!method_exists($classname, 'deposit')) {
                $this->error = "通道类未实现deposit方法:{$classname}";
                $logService->excute($order->id, $this->modeText() . "，请求渠道【" . ($ch['name'] ?? null) . "】, 请求失败", $this->error, 'error');
                return null;
            }

            $pay = new $classname(LogConstService::DEPOSIT_ORDER_LOG_PREFIX . $order->id);
            $row = $pay->deposit($order->id);

            if ($this->needTouchAfter()) {
                App::make(DepositChannelPriorityService::class)->touch($ch, $logService, $order->id);
            }

            if (!empty($row)) {
                if (intval($ch['channel_id'] ?? 0) === 1) {
                    $reserved = App::make(ReserveUserDepositOrderService::class)->execute(
                        intval($order->id),
                        intval($row['user_id'] ?? 0),
                        intval($row['user_bank_id'] ?? 0),
                        floatval(bob_admin_setting('push_pay_order_total_amount')),
                        intval(bob_admin_setting('push_pay_order_togather_amount')),
                        [
                            'same_amount_minutes' => intval(bob_admin_setting('push_advance_order_time')),
                            'same_amount_count' => intval(bob_admin_setting('push_cannel_or_cancel_order_number')),
                            'pending_minutes' => intval(bob_admin_setting('pending_pay_order_time')),
                            'pending_count' => intval(bob_admin_setting('pending_pay_order_number')),
                        ]
                    );
                    if (!$reserved) {
                        $sameAmountTimeService = App::make(GetUserBankSameAmountTimeService::class);
                        $userBankId = intval($row['user_bank_id'] ?? 0);
                        $sameAmountTimeService->forget($userBankId, floatval($order->amount));
                        if (floatval($order->pay_amount) !== floatval($order->amount)) {
                            $sameAmountTimeService->forget($userBankId, floatval($order->pay_amount));
                        }
                        $this->error = '金主保证金或待付款额度不足';
                        $logService->excute($order->id, $this->modeText() . '，自营派单保证金预占失败', [
                            'user_id' => intval($row['user_id'] ?? 0),
                            'user_bank_id' => intval($row['user_bank_id'] ?? 0),
                            'amount' => $order->amount,
                        ], 'error');

                        return null;
                    }

                    // 最终预占事务已重新读取并锁定费率/代理链，覆盖支付包较早生成的快照。
                    $snapshot = \App\Models\DepositOrder::query()->whereKey($order->id)->first([
                        'user_rate',
                        'user_agent1_rate',
                        'user_agent2_rate',
                        'user_agent3_rate',
                        'user_agent4_rate',
                        'user_agent5_rate',
                        'user_agent1_id',
                        'user_agent2_id',
                        'user_agent3_id',
                        'user_agent4_id',
                        'user_agent5_id',
                    ]);
                    if ($snapshot) {
                        $row = array_merge($row, $snapshot->only([
                            'user_rate',
                            'user_agent1_rate',
                            'user_agent2_rate',
                            'user_agent3_rate',
                            'user_agent4_rate',
                            'user_agent5_rate',
                            'user_agent1_id',
                            'user_agent2_id',
                            'user_agent3_id',
                            'user_agent4_id',
                            'user_agent5_id',
                        ]));
                    }
                }

                $logService->excute($order->id, $this->modeText() . "，请求渠道【" . ($ch['name'] ?? null) . "】, 成功返回", $row, 'debug');

                $result = Arr::collapse([
                    $row,
                    [
                        'merchant_extra_fee' => $ch['merchant_extra_fee'],
                        'settlement_mode' => $ch['settlement_mode'],
                        'settlement_time' => bob_settlement_time($ch['settlement_mode'], $ch['settlement_time']),
                    ]
                ]);

                return Arr::collapse([['status' => 3], $result]);
            }

            // 失败：+ 通知 + 日志
            $this->error = $pay->error ?: '渠道返回错误';

            $logService->excute($order->id, $this->modeText() . "，请求渠道【" . ($ch['name'] ?? null) . "】, 请求失败", $this->error, 'error');

            bob_send_channel_exception_notice([
                'error' => $this->error,
                'ordernumber' => $order->ordernumber,
                'title' => '通道调用异常报警',
                'channel_name' => $ch['name'] ?? '',
                'action' => '代收订单渠道调用错误'
            ]);

            return null;

        } catch (\Throwable $e) {
            $this->error = $e->getMessage();

            $logService->excute($order->id, $this->modeText() . "，请求渠道【" . ($ch['name'] ?? null) . "】, 请求失败", $this->error, 'error');

            return null;
        }
    }

    protected function updateOrderChannel($order, int $channelId): void
    {
        if ((int)$order->channel_id === $channelId) {
            return;
        }

        $order->fill(['channel_id' => $channelId]);
        $order->save();
        App::make(OrderCacheService::class)->putDeposit($order);
    }

    private function applyFinalRateAndChannel($order, array $channel, $logService): bool
    {
        $saveData = [
            'mid' => $order->mid,
            'payment_id' => $order->payment_id,
            'amount' => $order->amount,
            'merchant_rate' => $order->merchant_rate,
            'merchant_agent1_rate' => $order->merchant_agent1_rate,
            'merchant_agent2_rate' => $order->merchant_agent2_rate,
            'merchant_agent3_rate' => $order->merchant_agent3_rate,
        ];
        $updateData = ['channel_id' => (int)($channel['channel_id'] ?? 0)];
        $rateResult = App::make(ApplyDepositChannelRateService::class)->excute($saveData, $updateData, $order->id, $logService);
        if (empty($rateResult['success'])) {
            $this->error = $rateResult['zh_message'] ?? $rateResult['message'] ?? '未匹配到通道费率';
            $logService->excute($order->id, $this->modeText() . '，渠道请求前代收费率匹配失败', [
                '错误原因' => $this->error,
                '渠道ID' => $channel['channel_id'] ?? 0,
                '渠道名称' => $channel['name'] ?? '',
                '订单金额' => $order->amount,
            ], 'error');

            return false;
        }

        $order->fill($updateData);
        $order->save();
        App::make(OrderCacheService::class)->putDeposit($order);

        return true;
    }
}
