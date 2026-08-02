<?php

namespace App\Services\TransferOrder;

use App\Jobs\MerchantTransferCallbackJob;
use App\Jobs\QueryChannelBalanceByIdJob;
use App\Jobs\TransferOrderFailNoticeTelegramGroupJob;
use App\Models\MerchantBalanceLog;
use App\Models\MerchantInfo;
use App\Models\TransferOrder;
use App\Services\Agent\AgentBalanceChangeService;
use App\Services\Cache\CacheConstPrefixService;
use App\Services\Channel\GetChannelNoticeBalanceService;
use App\Services\Merchant\MerchantBalanceChangeService;
use App\Services\User\UserBalanceChangeService;
use App\Services\User\UserAgentBalanceChangeService;
use App\Services\User\UserTodayStatsRebuildService;
use App\Services\Report\OrderStatusReportRepairService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

class TransferOrderReverseService
{
    public function failTransfer($order_id = 0, $remark = '', $hand_admin_id = 0): TransferOrder
    {
        return $this->reverse($order_id, [
            'type' => 0,
            'allow_status' => [1, 2, 3],
            'source_log_type' => 2,
            'reverse_log_type' => 3,
            'user_amount_reverse_type' => 8,
            'user_amount_reverse_remark' => '代付本金冲正',
            'user_commission_reverse_type' => 11,
            'user_commission_reverse_remark' => '代付佣金冲正',
            'user_agent_commission_reverse_type' => 5,
            'merchant_agent_commission_reverse_type' => 5,
            'merchant_agent_commission_reverse_remark' => '代付佣金冲正',
            'remark' => $remark,
            'hand_admin_id' => $hand_admin_id,
            'notice' => 'transfer_fail',
            'callback' => true,
            'query_channel_balance' => true,
        ]);
    }

    public function correTransfer($order_id = 0, $remark = '', $hand_admin_id = 0): TransferOrder
    {
        return $this->reverse($order_id, [
            'type' => 0,
            'allow_status' => [4],
            'source_log_type' => 2,
            'reverse_log_type' => 5,
            'user_amount_reverse_type' => 8,
            'user_amount_reverse_remark' => '代付本金冲正',
            'user_commission_reverse_type' => 11,
            'user_commission_reverse_remark' => '代付佣金冲正',
            'user_agent_commission_reverse_type' => 5,
            'merchant_agent_commission_reverse_type' => 5,
            'merchant_agent_commission_reverse_remark' => '代付佣金冲正',
            'remark' => $remark,
            'hand_admin_id' => $hand_admin_id,
            'notice' => '',
            'callback' => false,
            'query_channel_balance' => false,
            'reset_centus' => true,
        ]);
    }

    public function failSettlement($order_id = 0, $remark = '', $hand_admin_id = 0): TransferOrder
    {
        return $this->reverse($order_id, [
            'type' => 1,
            'allow_status' => [2, 3],
            'source_log_type' => 6,
            'reverse_log_type' => 7,
            'user_amount_reverse_type' => 15,
            'user_amount_reverse_remark' => '结算本金冲正',
            'user_commission_reverse_type' => 15,
            'user_commission_reverse_remark' => '结算佣金冲正',
            'user_agent_commission_reverse_type' => 8,
            'merchant_agent_commission_reverse_type' => 8,
            'merchant_agent_commission_reverse_remark' => '结算佣金冲正',
            'remark' => $remark,
            'hand_admin_id' => $hand_admin_id,
            'notice' => 'settlement_fail',
            'callback' => false,
            'query_channel_balance' => false,
            'reset_centus' => true,
        ]);
    }

    public function correSettlement($order_id = 0, $remark = '', $hand_admin_id = 0): TransferOrder
    {
        return $this->reverse($order_id, [
            'type' => 1,
            'allow_status' => [4],
            'source_log_type' => 6,
            'reverse_log_type' => 15,
            'user_amount_reverse_type' => 15,
            'user_amount_reverse_remark' => '结算本金冲正',
            'user_commission_reverse_type' => 15,
            'user_commission_reverse_remark' => '结算佣金冲正',
            'user_agent_commission_reverse_type' => 8,
            'merchant_agent_commission_reverse_type' => 8,
            'merchant_agent_commission_reverse_remark' => '结算佣金冲正',
            'remark' => $remark,
            'hand_admin_id' => $hand_admin_id,
            'decrement_settlement_amount' => true,
            'notice' => '',
            'callback' => false,
            'query_channel_balance' => false,
        ]);
    }

    protected function reverse($order_id, array $config): TransferOrder
    {
        return DB::transaction(function () use ($order_id, $config) {
            $order = TransferOrder::where('id', $order_id)
                ->where('type', $config['type'])
                ->whereIn('status', $config['allow_status'])
                ->lockForUpdate()
                ->first($this->fields());

            if (!$order) {
                throw new \Exception('订单不存在或当前状态无法冲正');
            }

            $remark = trim((string) ($config['remark'] ?? ''));
            $order->status = 5;
            $order->hand_admin_id = intval($config['hand_admin_id'] ?? 0);
            if ($remark !== '') {
                $order->remark = $remark;
            }
            $order->save();

            $this->reverseUserTransferAmount($order, $config);
            $this->reverseUserCommission($order, $config);
            $this->reverseMerchantAgentCommission($order, $config);

            $this->reverseMerchantBalance($order, $config, $remark);

            if (!empty($config['decrement_settlement_amount'])) {
                MerchantInfo::where('merchant_user_id', $order->mid)->decrement('settlement_amount', $order->amount);
            }

            DB::afterCommit(function () use ($order, $config) {
                cache_transfer_info($order);

                if (!empty($config['callback'])) {
                    dispatch(new MerchantTransferCallbackJob($order->id))->onQueue('callback');
                }

                if (($config['notice'] ?? '') === 'transfer_fail') {
                    bob_send_system_transfer_notice(['error_text' => '代付订单失败，订单号：'.$order->ordernumber, 'voice_id' => 'transfer_5', 'id' => 5]);
                    if (intval(bob_admin_setting("telegram_transfor_order_fail_notice_telegram_group_on")) == 1) {
                        dispatch(new TransferOrderFailNoticeTelegramGroupJob(['id' => $order->id, 'telegram_bot_token' => config("telegram.telegram_bot_token"), 'telegram_turn_on' => intval(config("telegram.turn_on", 0))]))->delay(now()->addSecond(5))->onQueue("notice");
                    }
                }

                if (($config['notice'] ?? '') === 'settlement_fail') {
                    bob_send_system_settlement_notice(['error_text' => '结算订单失败，订单号：'.$order->ordernumber, 'voice_id' => 'settlement_5', 'id' => 5]);
                    if (intval(bob_admin_setting("telegram_transfor_order_fail_notice_telegram_group_on")) == 1) {
                        dispatch(new TransferOrderFailNoticeTelegramGroupJob(['id' => $order->id, 'telegram_bot_token' => config("telegram.telegram_bot_token"), 'telegram_turn_on' => intval(config("telegram.turn_on", 0))]))->delay(now()->addSecond(5))->onQueue("notice");
                    }
                }

                if (!empty($config['query_channel_balance']) && intval($order->channel_id) > 1 && App::make(GetChannelNoticeBalanceService::class)->enabled((int) $order->channel_id)) {
                    dispatch(new QueryChannelBalanceByIdJob((int) $order->channel_id))->onQueue('query');
                }

                if (!empty($config['reset_centus'])) {
                    App::make(TransferOrderCentusResetService::class)->excute($order);
                }

                $this->rebuildTodayStats($order);
                App::make(OrderStatusReportRepairService::class)->forTransferOrder($order);
            });

            return $order;
        });
    }

    private function rebuildTodayStats(TransferOrder $order): void
    {
        if (intval($order->success_time ?? 0) <= 0 || date('Y-m-d', (int) $order->success_time) !== date('Y-m-d')) {
            return;
        }

        $userIds = [
            (int) $order->user_id,
            (int) $order->user_agent1_id,
            (int) $order->user_agent2_id,
            (int) $order->user_agent3_id,
            (int) $order->user_agent4_id,
            (int) $order->user_agent5_id,
        ];

        foreach (array_unique(array_filter($userIds)) as $userId) {
            App::make(UserTodayStatsRebuildService::class)->rebuild((int) $userId);
        }
    }

    protected function reverseMerchantBalance(TransferOrder $order, array $config, string $remark = ''): void
    {
        $originLog = MerchantBalanceLog::where('type', $config['source_log_type'])
            ->where('type_id', $order->id)
            ->where('ordernumber', $order->ordernumber)
            ->lockForUpdate()
            ->first(['id', 'fee']);

        if (!$originLog) {
            return;
        }

        $reverseRemark = "冲正：" . $order->ordernumber;
        if ($remark !== '') {
            $reverseRemark .= "，备注：" . $remark;
        }

        $merchantBalanceChangeService = App::make(MerchantBalanceChangeService::class);
        $result = $merchantBalanceChangeService->excute([
            'mid' => $order->mid,
            'amount' => $order->amount,
            'fee' => -bob_amount_format($originLog->fee),
            'type' => $config['reverse_log_type'],
            'type_id' => $order->id,
            'currency_id' => $order->currency_id,
            'payment_id' => 7,
            'order_type' => 2,
            'remark' => $reverseRemark,
            'admin_id' => (int) ($config['hand_admin_id'] ?? 0),
            'ordernumber' => $order->ordernumber,
            'order_no' => $order->order_no,
        ]);

        if (empty($result['success'])) {
            throw new \Exception($result['message'] ?? '商户余额冲正失败');
        }

        $reverseLogId = (int) $merchantBalanceChangeService->merchant_balance_log_id;
        if ($reverseLogId <= 0) {
            throw new \Exception('商户余额冲正流水生成失败');
        }
    }

    protected function reverseUserCommission(TransferOrder $order, array $config): void
    {
        if ($order->user_id <= 0) {
            return;
        }

        $userCommissionType = (int) ($config['user_commission_reverse_type'] ?? 11);
        $userAgentCommissionType = (int) ($config['user_agent_commission_reverse_type'] ?? 5);
        $service = App::make(UserBalanceChangeService::class);
        if ($order->user_commission > 0) {
            $service->excute([
                'user_id' => $order->user_id,
                'mid' => $order->mid,
                'amount' => $order->user_commission,
                'type' => $userCommissionType,
                'type_id' => $order->id,
                'ordernumber' => $order->ordernumber,
                'order_type' => 2,
                'balance_account' => 'commission',
                'remark' => $config['user_commission_reverse_remark'] ?? '',
                'action_user_id' => (int) ($config['hand_admin_id'] ?? 0),
            ]);
        }

        $this->reverseUserAgentCommission($order, $userAgentCommissionType, $config);
    }

    protected function reverseUserAgentCommission(TransferOrder $order, int $type, array $config): void
    {
        $items = [
            [$order->user_agent1_id, $order->user_agent1_commission],
            [$order->user_agent2_id, $order->user_agent2_commission],
            [$order->user_agent3_id, $order->user_agent3_commission],
            [$order->user_agent4_id, $order->user_agent4_commission],
            [$order->user_agent5_id, $order->user_agent5_commission],
        ];

        $service = App::make(UserAgentBalanceChangeService::class);
        foreach ($items as [$userId, $commission]) {
            if ($userId <= 0 || $commission <= 0) {
                continue;
            }

            $service->excute([
                'user_id' => $userId,
                'mid' => $order->mid,
                'amount' => $commission,
                'type' => $type,
                'type_id' => $order->id,
                'ordernumber' => $order->ordernumber,
                'order_type' => 2,
                'remark' => $config['user_commission_reverse_remark'] ?? '',
                'action_user_id' => (int) ($config['hand_admin_id'] ?? 0),
            ]);
        }
    }

    protected function reverseUserTransferAmount(TransferOrder $order, array $config): void
    {
        if ($order->user_id <= 0 || $order->actual_amount <= 0) {
            return;
        }

        App::make(UserBalanceChangeService::class)->excute([
            'user_id' => $order->user_id,
            'mid' => $order->mid,
            'amount' => $order->actual_amount,
            'type' => (int) ($config['user_amount_reverse_type'] ?? 8),
            'type_id' => $order->id,
            'ordernumber' => $order->ordernumber,
            'order_type' => 2,
            'balance_account' => 'transfer',
            'remark' => $config['user_amount_reverse_remark'] ?? '',
            'action_user_id' => (int) ($config['hand_admin_id'] ?? 0),
        ]);
    }

    protected function reverseMerchantAgentCommission(TransferOrder $order, array $config): void
    {
        $type = (int) ($config['merchant_agent_commission_reverse_type'] ?? 5);
        $items = [
            [$order->merchant_agent1_id, $order->merchant_agent1_commission],
            [$order->merchant_agent2_id, $order->merchant_agent2_commission],
            [$order->merchant_agent3_id, $order->merchant_agent3_commission],
        ];

        $service = App::make(AgentBalanceChangeService::class);
        foreach ($items as [$agentId, $commission]) {
            if ($agentId > 0 && $commission > 0) {
                $service->excute([
                    'type' => $type,
                    'type_id' => $order->id,
                    'agent_id' => $agentId,
                    'mid' => $order->mid,
                    'ordernumber' => $order->ordernumber,
                    'amount' => $commission,
                    'remark' => $config['merchant_agent_commission_reverse_remark'] ?? '',
                    'action_agent_id' => (int) ($config['hand_admin_id'] ?? 0),
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
            'profit',
            'channel_rate',
            'channel_cost',
            'user_id',
            'order_no',
            'ordernumber',
        ], CacheConstPrefixService::CACHE_TRANSFER_FILED);
    }
}
