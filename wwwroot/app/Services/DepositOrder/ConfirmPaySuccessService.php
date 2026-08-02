<?php

namespace App\Services\DepositOrder;

use App\Models\UserBank;
use App\Models\DepositOrder;
use App\Traits\ServiceTraits;
use App\Models\UserBankBalanceLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use App\Jobs\MerchantDepositCallbackJob;
use App\Services\Cache\CacheConstPrefixService;
use App\Services\Common\ReportExceptionService;
use App\Services\User\UserBalanceChangeService;
use App\Services\User\UserAgentBalanceChangeService;
use App\Services\Agent\AgentBalanceChangeService;
use App\Services\Merchant\MerchantBalanceChangeService;
use App\Services\Cache\ChannelRate\GetChannelRateDetailService;

class ConfirmPaySuccessService
{
    use ServiceTraits;

    private const TRANSACTION_ATTEMPTS = 3;

    public function excute($order_id = 0, $amount = 0, $callback = true, $remark = '', int $handAdminId = 0, int $handSuccess = 0)
    {
        try {
            return DB::transaction(function () use ($order_id, $amount, $callback, $remark, $handAdminId, $handSuccess) {
                $order = DepositOrder::where('id', $order_id)->lockForUpdate()->first($this->fields());

                if (!$order) {
                    throw new \Exception("订单不存在或当前状态无法确认成功");
                }

                if (intval($order->status) === 5) {
                    return $order;
                }

                if (!in_array(intval($order->status), [1, 3, 4, 7], true)) {
                    throw new \Exception("订单不存在或当前状态无法确认成功");
                }

                $data = $this->successData($order, $amount, $remark, $handAdminId, $handSuccess);
                $order->fill($data);
                $order->save();

                $this->changeUserBalance($order, $data);
                $this->changeUserBankBalance($order, $data);
                $this->changeMerchantAgentBalance($order, $data);
                $this->changeMerchantBalance($order, $data, $remark);
                $this->afterCommitSuccess($order, (bool) $callback);

                return $order;
            }, self::TRANSACTION_ATTEMPTS);
        } catch (\Exception $e) {
            app(ReportExceptionService::class)->report('代收订单确认成功发生异常', $e, [
                'order_id' => $order_id,
                'amount' => $amount,
                'callback' => $callback,
            ]);
            throw new \Exception($e->getMessage());
        }
    }

    protected function successData(DepositOrder $order, $amount, string $remark = '', int $handAdminId = 0, int $handSuccess = 0): array
    {
        $actualAmount = floatval($amount);
        $data = [
            'actual_amount' => $actualAmount,
            'status' => 5,
            'success_time' => time(),
            'merchant_fee' => bob_amount_format($actualAmount * floatval($order->merchant_rate)),
            'merchant_agent1_commission' => $order->merchant_agent1_id > 0 ? bob_amount_format($actualAmount * floatval($order->merchant_agent1_rate)) : 0,
            'merchant_agent2_commission' => $order->merchant_agent2_id > 0 ? bob_amount_format($actualAmount * floatval($order->merchant_agent2_rate)) : 0,
            'merchant_agent3_commission' => $order->merchant_agent3_id > 0 ? bob_amount_format($actualAmount * floatval($order->merchant_agent3_rate)) : 0,
            'user_commission' => 0,
            'user_agent1_commission' => 0,
            'user_agent2_commission' => 0,
            'user_agent3_commission' => 0,
            'user_agent4_commission' => 0,
            'user_agent5_commission' => 0,
        ];

        if ($remark !== '') {
            $data['remark'] = $remark;
        }

        if ($handSuccess > 0 || $handAdminId > 0) {
            $data['hand_success'] = $handSuccess;
            $data['hand_admin_id'] = $handAdminId;
        }

        if ($order->user_id > 0) {
            $data['user_commission'] = bob_amount_format($actualAmount * floatval($order->user_rate));
            $data['user_agent1_commission'] = $order->user_agent1_id > 0 ? bob_amount_format($actualAmount * floatval($order->user_agent1_rate)) : 0;
            $data['user_agent2_commission'] = $order->user_agent2_id > 0 ? bob_amount_format($actualAmount * floatval($order->user_agent2_rate)) : 0;
            $data['user_agent3_commission'] = $order->user_agent3_id > 0 ? bob_amount_format($actualAmount * floatval($order->user_agent3_rate)) : 0;
            $data['user_agent4_commission'] = $order->user_agent4_id > 0 ? bob_amount_format($actualAmount * floatval($order->user_agent4_rate)) : 0;
            $data['user_agent5_commission'] = $order->user_agent5_id > 0 ? bob_amount_format($actualAmount * floatval($order->user_agent5_rate)) : 0;
        }

        $channelRateService = App::make(GetChannelRateDetailService::class);
        $data['channel_rate'] = $channelRateService->excute($order->channel_id, $order->payment_id, 0, $actualAmount);
        $data['channel_cost'] = floatval(sprintf("%.6f", $channelRateService->calculateCost($order->channel_id, $order->payment_id, $actualAmount)));
        $data['profit'] = sprintf("%.6f", (
            floatval($data['merchant_fee'])
            + floatval($order->merchant_extra_fee)
            - $data['channel_cost']
            - $data['merchant_agent1_commission']
            - $data['merchant_agent2_commission']
            - $data['merchant_agent3_commission']
            - $data['user_commission']
            - $data['user_agent1_commission']
            - $data['user_agent2_commission']
            - $data['user_agent3_commission']
            - $data['user_agent4_commission']
            - $data['user_agent5_commission']
        ));

        return $data;
    }

    protected function changeUserBalance(DepositOrder $order, array $data): void
    {
        if ($order->user_id <= 0) {
            return;
        }

        $service = App::make(UserBalanceChangeService::class);
        $service->excute([
            'user_id' => $order->user_id,
            'mid' => $order->mid,
            'amount' => -floatval($data['actual_amount']),
            'type' => 4,
            'type_id' => $order->id,
            'ordernumber' => $order->ordernumber,
            'order_type' => 1,
        ]);

        if (floatval($data['user_commission']) > 0) {
            $service->excute([
                'user_id' => $order->user_id,
                'mid' => $order->mid,
                'amount' => floatval($data['user_commission']),
                'type' => 1,
                'type_id' => $order->id,
                'ordernumber' => $order->ordernumber,
                'order_type' => 1,
            ]);
        }

        $this->changeUserAgentCommissionBalance($order, $data, 1);
    }

    protected function changeUserAgentCommissionBalance(DepositOrder $order, array $data, int $type): void
    {
        $items = [
            [$order->user_agent1_id, $data['user_agent1_commission']],
            [$order->user_agent2_id, $data['user_agent2_commission']],
            [$order->user_agent3_id, $data['user_agent3_commission']],
            [$order->user_agent4_id, $data['user_agent4_commission']],
            [$order->user_agent5_id, $data['user_agent5_commission']],
        ];

        $service = App::make(UserAgentBalanceChangeService::class);
        foreach ($items as [$userId, $commission]) {
            if ($userId <= 0 || floatval($commission) <= 0) {
                continue;
            }

            $service->excute([
                'user_id' => $userId,
                'mid' => $order->mid,
                'amount' => floatval($commission),
                'type' => $type,
                'type_id' => $order->id,
                'ordernumber' => $order->ordernumber,
                'order_type' => 1,
            ]);
        }
    }

    protected function changeUserBankBalance(DepositOrder $order, array $data): void
    {
        if ($order->user_bank_id <= 0) {
            return;
        }

        $userBank = UserBank::where('id', $order->user_bank_id)
            ->lockForUpdate()
            ->first(['id', 'balance_amount']);
        if (!$userBank) {
            return;
        }

        $userBank->balance_amount = bob_amount_format($userBank->balance_amount + floatval($data['actual_amount']));
        $userBank->save();

        UserBankBalanceLog::create([
            'amount' => $data['actual_amount'],
            'user_id' => $order->user_id,
            'user_bank_id' => $order->user_bank_id,
            'type' => 1,
            'type_id' => $order->id,
            'balance_amount' => $userBank->balance_amount,
        ]);
    }

    protected function changeMerchantAgentBalance(DepositOrder $order, array $data): void
    {
        $items = [
            [$order->merchant_agent1_id, $data['merchant_agent1_commission']],
            [$order->merchant_agent2_id, $data['merchant_agent2_commission']],
            [$order->merchant_agent3_id, $data['merchant_agent3_commission']],
        ];

        $service = App::make(AgentBalanceChangeService::class);
        foreach ($items as [$agentId, $commission]) {
            if ($agentId > 0 && floatval($commission) > 0) {
                $service->excute([
                    'type' => 1,
                    'type_id' => $order->id,
                    'agent_id' => $agentId,
                    'mid' => $order->mid,
                    'ordernumber' => $order->ordernumber,
                    'amount' => floatval($commission),
                ]);
            }
        }
    }

    protected function changeMerchantBalance(DepositOrder $order, array $data, string $remark = ''): void
    {
        $result = App::make(MerchantBalanceChangeService::class)->excute([
            'mid' => $order->mid,
            'amount' => $data['actual_amount'],
            'fee' => $data['merchant_fee'] + $order->merchant_extra_fee,
            'type' => 1,
            'type_id' => $order->id,
            'admin_id' => intval($data['hand_admin_id'] ?? $order->hand_admin_id ?? 0),
            'currency_id' => $order->currency_id,
            'payment_id' => $order->payment_id,
            'order_type' => 1,
            'settlement_mode' => $order->settlement_mode,
            'settlement_time' => $order->settlement_time,
            'ordernumber' => $order->ordernumber,
            'order_no' => $order->order_no,
            'remark' => $remark,
        ]);

        if (empty($result['success'])) {
            throw new \Exception($result['message'] ?? '商户余额变化失败');
        }
    }

    protected function afterCommitSuccess(DepositOrder $order, bool $callback): void
    {
        DB::afterCommit(function () use ($order, $callback) {
            App::make(CheckDepositOrderRefreshOrderService::class)->release((int) $order->id);
            App::make(HandleDepositOrderSuccessService::class)->excute($order);

            if ($callback) {
                dispatch(new MerchantDepositCallbackJob($order->id))->onQueue('callback');
            }
        });
    }

    protected function fields(): array
    {
        return array_merge([
            'pay_amount',
            'actual_amount',
            'merchant_fee',
            'merchant_agent1_commission',
            'merchant_agent2_commission',
            'merchant_agent3_commission',
            'status',
            'success_time',
            'user_agent1_commission',
            'user_commission',
            'user_agent2_commission',
            'user_agent3_commission',
            'user_agent4_commission',
            'user_agent5_commission',
            'merchant_rate',
            'merchant_agent1_rate',
            'merchant_agent2_rate',
            'merchant_agent3_rate',
            'user_agent1_rate',
            'user_rate',
            'user_agent2_rate',
            'user_agent3_rate',
            'user_agent4_rate',
            'user_agent5_rate',
            'user_id',
            'mid',
            'id',
            'user_agent1_id',
            'user_agent2_id',
            'user_agent3_id',
            'user_agent4_id',
            'user_agent5_id',
            'user_bank_id',
            'merchant_agent1_id',
            'merchant_agent2_id',
            'merchant_agent3_id',
            'merchant_extra_fee',
            'currency_id',
            'settlement_mode',
            'settlement_time',
            'ordernumber',
            'payment_id',
            'channel_id',
            'profit',
            'channel_rate',
            'channel_cost',
            'order_no',
            'created_at',
            'hand_success',
            'hand_admin_id',
            'remark',
        ], CacheConstPrefixService::CACHE_DEPOSIT_FILED);
    }
}
