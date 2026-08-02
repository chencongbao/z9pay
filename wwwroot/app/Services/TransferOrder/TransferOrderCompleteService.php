<?php

namespace App\Services\TransferOrder;

use App\Models\MerchantInfo;
use App\Models\TransferOrder;
use App\Models\MerchantBalanceLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use App\Jobs\MerchantTransferCallbackJob;
use App\Services\Order\OrderCacheService;
use App\Services\Cache\CacheConstPrefixService;
use App\Services\User\UserBalanceChangeService;
use App\Services\User\UserAgentBalanceChangeService;
use App\Services\Agent\AgentBalanceChangeService;
use App\Services\Merchant\MerchantBalanceChangeService;
use App\Services\MerchantPayment\MerchantOrderRateService;
use App\Services\Cache\ChannelRate\GetChannelRateDetailService;

class TransferOrderCompleteService
{
    public function successTransfer($order_id = 0, $amount = 0, $remark = '', $hand_admin_id = 0, $hand_success = 0): TransferOrder
    {
        return $this->success($order_id, $amount, $remark, $hand_admin_id, $hand_success, [
            'type' => 0,
            'allow_status' => [1, 2, 3],
            'user_amount_type' => 7,
            'user_commission_type' => 13,
            'user_agent_commission_type' => 2,
            'merchant_agent_types' => [2, 2, 2],
            'increment_settlement_amount' => false,
            'after_success_do' => true,
            'merchant_callback' => true,
        ]);
    }

    public function successSettlement($order_id = 0, $amount = 0, $remark = '', $hand_admin_id = 0, $hand_success = 0): TransferOrder
    {
        return $this->success($order_id, $amount, $remark, $hand_admin_id, $hand_success, [
            'type' => 1,
            'allow_status' => [2, 3, 7],
            'user_amount_type' => 16,
            'user_commission_type' => 14,
            'user_agent_commission_type' => 7,
            'merchant_agent_types' => [7, 7, 7],
            'increment_settlement_amount' => true,
            'after_success_do' => false,
            'merchant_callback' => false,
        ]);
    }

    protected function success($order_id, $amount, $remark, $hand_admin_id, $hand_success, array $config): TransferOrder
    {
        return DB::transaction(function () use ($order_id, $amount, $remark, $hand_admin_id, $hand_success, $config) {
            $order = TransferOrder::where('id', $order_id)
                ->where('type', $config['type'])
                ->whereIn('status', $config['allow_status'])
                ->lockForUpdate()
                ->first($this->fields());

            if (!$order) {
                throw new \Exception('订单不存在或当前状态无法确认成功');
            }

            $data = $this->successData($order, $amount, $remark, $hand_admin_id, $hand_success, (int) $config['type']);
            $order->fill($data);
            $order->save();

            $skipBalanceChange = $this->shouldSkipBalanceChange($order, (int) $config['type']);

            if ((int) $config['type'] === 0 && ! $skipBalanceChange) {
                $this->ensureMerchantTransferDeducted($order, $data, (int) $hand_admin_id);
            }

            if (! $skipBalanceChange) {
                $this->changeUserBalance($order, $data, (int) $config['user_amount_type'], (int) $config['user_commission_type'], (int) $config['user_agent_commission_type'], (int) $hand_admin_id);
                $this->changeMerchantAgentBalance($order, $data, $config['merchant_agent_types'], (int) $hand_admin_id);
            }

            if (!empty($config['increment_settlement_amount'])) {
                MerchantInfo::where('merchant_user_id', $order->mid)->increment('settlement_amount', $order->amount);
            }

            DB::afterCommit(function () use ($order, $config) {
                if (!empty($config['after_success_do'])) {
                    App::make(HandleTransferOrderSuccessDoService::class)->excute($order);
                } else {
                    App::make(OrderCacheService::class)->putTransfer($order, true);
                }

                if (!empty($config['merchant_callback'])) {
                    dispatch(new MerchantTransferCallbackJob($order->id))->onQueue('callback');
                }
            });

            return $order;
        });
    }

    protected function successData(TransferOrder $order, $amount, $remark, $hand_admin_id, $hand_success, int $type): array
    {
        $data = [
            'status' => 4,
            'actual_amount' => $amount,
            'success_time' => time(),
            'remark' => $remark,
            'hand_success' => $hand_success,
            'hand_admin_id' => $hand_admin_id,
        ];

        if ($type === 0 && $this->isTestTransferOrder($order)) {
            $this->fillTestTransferZeroRate($order, $data);
        } elseif ($type === 0) {
            $this->fillTransferSuccessRate($order, $data);
        }

        $data['merchant_fee'] = bob_amount_format($data['actual_amount'] * $order->merchant_rate);
        $data['merchant_agent1_commission'] = $order->merchant_agent1_id > 0 ? bob_amount_format($data['actual_amount'] * $order->merchant_agent1_rate) : 0;
        $data['merchant_agent2_commission'] = $order->merchant_agent2_id > 0 ? bob_amount_format($data['actual_amount'] * $order->merchant_agent2_rate) : 0;
        $data['merchant_agent3_commission'] = $order->merchant_agent3_id > 0 ? bob_amount_format($data['actual_amount'] * $order->merchant_agent3_rate) : 0;

        $data['user_commission'] = 0;
        $data['user_agent1_commission'] = 0;
        $data['user_agent2_commission'] = 0;
        $data['user_agent3_commission'] = 0;
        $data['user_agent4_commission'] = 0;
        $data['user_agent5_commission'] = 0;
        if ($order->user_id > 0) {
            $data['user_commission'] = bob_amount_format($data['actual_amount'] * $order->user_rate);
            $data['user_agent1_commission'] = $order->user_agent1_id > 0 ? bob_amount_format($data['actual_amount'] * $order->user_agent1_rate) : 0;
            $data['user_agent2_commission'] = $order->user_agent2_id > 0 ? bob_amount_format($data['actual_amount'] * $order->user_agent2_rate) : 0;
            $data['user_agent3_commission'] = $order->user_agent3_id > 0 ? bob_amount_format($data['actual_amount'] * $order->user_agent3_rate) : 0;
            $data['user_agent4_commission'] = $order->user_agent4_id > 0 ? bob_amount_format($data['actual_amount'] * $order->user_agent4_rate) : 0;
            $data['user_agent5_commission'] = $order->user_agent5_id > 0 ? bob_amount_format($data['actual_amount'] * $order->user_agent5_rate) : 0;
        }

        if ($type === 0 && $this->isTestTransferOrder($order)) {
            $data['channel_rate'] = 0;
            $data['channel_cost'] = 0;
        } else {
            $channelRateService = App::make(GetChannelRateDetailService::class);
            $data['channel_rate'] = $channelRateService->excute($order->channel_id, 7, 0, $data['actual_amount']);
            $data['channel_cost'] = floatval(sprintf("%.6f", $channelRateService->calculateCost($order->channel_id, 7, $data['actual_amount'])));
        }
        $data['profit'] = sprintf("%.6f", (
            floatval($data['merchant_fee'])
            + floatval($data['merchant_extra_fee'] ?? $order->merchant_extra_fee)
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

    protected function isTestTransferOrder(TransferOrder $order): bool
    {
        return strtolower(trim((string) $order->bank_code)) === 'test';
    }

    protected function shouldSkipBalanceChange(TransferOrder $order, int $type): bool
    {
        return $type === 0 && $this->isTestTransferOrder($order);
    }

    protected function fillTestTransferZeroRate(TransferOrder $order, array &$data): void
    {
        $data['merchant_rate'] = $order->merchant_rate = 0;
        $data['merchant_agent1_rate'] = $order->merchant_agent1_rate = 0;
        $data['merchant_agent2_rate'] = $order->merchant_agent2_rate = 0;
        $data['merchant_agent3_rate'] = $order->merchant_agent3_rate = 0;
        $data['user_rate'] = $order->user_rate = 0;
        $data['user_agent1_rate'] = $order->user_agent1_rate = 0;
        $data['user_agent2_rate'] = $order->user_agent2_rate = 0;
        $data['user_agent3_rate'] = $order->user_agent3_rate = 0;
        $data['user_agent4_rate'] = $order->user_agent4_rate = 0;
        $data['user_agent5_rate'] = $order->user_agent5_rate = 0;
        $data['merchant_extra_fee'] = $order->merchant_extra_fee = 0;
    }

    protected function fillTransferSuccessRate(TransferOrder $order, array &$data): void
    {
        $rateData = [
            'mid' => $order->mid,
            'bank_id' => $order->bank_id,
            'amount' => $data['actual_amount'],
        ];

        $result = App::make(MerchantOrderRateService::class)->fillTransferFinalRate($rateData, (int) $order->channel_id);
        if (empty($result['success'])) {
            throw new \Exception($result['zh_message'] ?? '未匹配到代付费率,请联系客服确认代付金额');
        }

        $data['merchant_rate'] = $order->merchant_rate = $rateData['merchant_rate'];
        $data['merchant_agent1_rate'] = $order->merchant_agent1_rate = $rateData['merchant_agent1_rate'];
        $data['merchant_agent2_rate'] = $order->merchant_agent2_rate = $rateData['merchant_agent2_rate'];
        $data['merchant_agent3_rate'] = $order->merchant_agent3_rate = $rateData['merchant_agent3_rate'];
    }

    protected function ensureMerchantTransferDeducted(TransferOrder $order, array $data, int $adminId = 0): void
    {
        if ($this->hasMerchantTransferDeductLog($order)) {
            return;
        }

        $fee = bob_amount_format(floatval($data['merchant_fee'] ?? 0) + floatval($data['merchant_extra_fee'] ?? $order->merchant_extra_fee));
        $result = App::make(MerchantBalanceChangeService::class)->deductTransferOrder($order, (float)$order->amount, (float)$fee, '代付手动成功扣款：' . $order->ordernumber, $adminId);

        if (empty($result['success'])) {
            throw new \Exception($result['message'] ?? '商户代付扣款失败');
        }
    }

    protected function hasMerchantTransferDeductLog(TransferOrder $order): bool
    {
        return MerchantBalanceLog::query()
            ->where('type', 2)
            ->where('type_id', $order->id)
            ->where('ordernumber', $order->ordernumber)
            ->exists();
    }

    protected function changeUserBalance(TransferOrder $order, array $data, int $userAmountType, int $userCommissionType, int $userAgentCommissionType, int $actionUserId = 0): void
    {
        if ($order->user_id <= 0) {
            return;
        }

        $service = App::make(UserBalanceChangeService::class);
        $service->excute([
            'user_id' => $order->user_id,
            'mid' => $order->mid,
            'amount' => floatval($data['actual_amount']),
            'type' => $userAmountType,
            'type_id' => $order->id,
            'ordernumber' => $order->ordernumber,
            'order_type' => 2,
            'action_user_id' => $actionUserId,
        ]);

        if (floatval($data['user_commission']) > 0) {
            $service->excute([
                'user_id' => $order->user_id,
                'mid' => $order->mid,
                'amount' => floatval($data['user_commission']),
                'type' => $userCommissionType,
                'type_id' => $order->id,
                'ordernumber' => $order->ordernumber,
                'order_type' => 2,
                'action_user_id' => $actionUserId,
            ]);
        }

        $this->changeUserAgentCommissionBalance($order, $data, $userAgentCommissionType, $actionUserId);
    }

    protected function changeUserAgentCommissionBalance(TransferOrder $order, array $data, int $type, int $actionUserId = 0): void
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
                'order_type' => 2,
                'action_user_id' => $actionUserId,
            ]);
        }
    }

    protected function changeMerchantAgentBalance(TransferOrder $order, array $data, array $types, int $actionAgentId = 0): void
    {
        $items = [
            [$order->merchant_agent1_id, $data['merchant_agent1_commission'], $types[0] ?? 0],
            [$order->merchant_agent2_id, $data['merchant_agent2_commission'], $types[1] ?? 0],
            [$order->merchant_agent3_id, $data['merchant_agent3_commission'], $types[2] ?? 0],
        ];

        $service = App::make(AgentBalanceChangeService::class);
        foreach ($items as [$agentId, $commission, $type]) {
            if ($agentId > 0 && floatval($commission) > 0) {
                $service->excute([
                    'type' => $type,
                    'type_id' => $order->id,
                    'agent_id' => $agentId,
                    'mid' => $order->mid,
                    'ordernumber' => $order->ordernumber,
                    'amount' => floatval($commission),
                    'action_agent_id' => $actionAgentId,
                ]);
            }
        }
    }

    protected function fields(): array
    {
        return array_merge([
            'id',
            'type',
            'status',
            'actual_amount',
            'success_time',
            'remark',
            'hand_success',
            'hand_admin_id',
            'merchant_fee',
            'merchant_agent1_commission',
            'merchant_agent2_commission',
            'merchant_agent3_commission',
            'merchant_rate',
            'merchant_agent1_rate',
            'merchant_agent2_rate',
            'merchant_agent3_rate',
            'user_commission',
            'user_agent1_commission',
            'user_agent2_commission',
            'user_agent3_commission',
            'user_agent4_commission',
            'user_agent5_commission',
            'user_rate',
            'user_agent1_rate',
            'user_agent2_rate',
            'user_agent3_rate',
            'user_agent4_rate',
            'user_agent5_rate',
            'mid',
            'user_agent1_id',
            'user_agent2_id',
            'user_agent3_id',
            'user_agent4_id',
            'user_agent5_id',
            'merchant_agent1_id',
            'merchant_agent2_id',
            'merchant_agent3_id',
            'currency_id',
            'merchant_extra_fee',
            'amount',
            'channel_id',
            'bank_code',
            'bank_id',
            'profit',
            'channel_rate',
            'channel_cost',
            'user_id',
            'order_no',
            'ordernumber',
        ], CacheConstPrefixService::CACHE_TRANSFER_FILED);
    }
}
