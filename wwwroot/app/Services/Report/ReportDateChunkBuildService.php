<?php

namespace App\Services\Report;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportDateChunkBuildService
{
    private const CHUNK_SIZE = 1000;

    private string $date;

    private string $startTime;

    private string $endTime;

    private int $startTimestamp;

    private int $endTimestamp;

    private string $merchantUsersTable;

    private array $activeMerchantIds = [];

    private array $stats = [];

    private array $userBalanceLogStats = [];

    private $heartbeat = null;

    private array $tableKeys = [
        'report_days' => [],
        'report_merchants' => ['mid'],
        'report_merchant_agents' => ['aid'],
        'report_users' => ['uid'],
        'report_user_agents' => ['aid'],
        'report_channels' => ['cid'],
        'report_payments' => ['pid'],
        'report_currencies' => ['cid'],
        'report_user_merchants' => ['mid', 'uid'],
        'report_channel_merchants' => ['mid', 'cid'],
        'report_payment_merchants' => ['mid', 'pid'],
        'report_currency_merchants' => ['mid', 'cid'],
        'report_user_banks' => ['uid', 'ubid'],
    ];

    private array $tableMetrics = [
        'report_days' => [
            'deposit_order_number_total', 'deposit_order_number_success', 'deposit_order_number_fail', 'deposit_order_number_overtime', 'deposit_order_number_swiping',
            'transfer_order_number_total', 'transfer_order_number_success', 'transfer_order_number_fail',
            'settlement_order_number_total', 'settlement_order_number_success', 'settlement_order_number_fail',
        ],
        'report_merchants' => [
            'deposit_order_number_total', 'deposit_order_number_success', 'deposit_order_number_fail', 'deposit_order_number_overtime', 'deposit_order_number_swiping',
            'deposit_order_total_amount', 'deposit_order_total_fee',
            'deposit_created_success_number', 'deposit_created_success_amount',
            'deposit_freeze_number', 'deposit_freeze_amount', 'deposit_unfreeze_number', 'deposit_unfreeze_amount',
            'transfer_order_number_total', 'transfer_order_number_success', 'transfer_order_number_fail', 'transfer_order_total_amount', 'transfer_order_total_fee',
            'transfer_created_success_number', 'transfer_created_success_amount',
            'transfer_deduct_number', 'transfer_deduct_amount', 'transfer_corre_number', 'transfer_corre_amount',
            'settlement_order_number_total', 'settlement_order_number_success', 'settlement_order_number_fail', 'settlement_order_total_amount', 'settlement_order_total_fee',
            'settlement_created_success_number', 'settlement_created_success_amount',
            'settlement_deduct_number', 'settlement_deduct_amount', 'settlement_corre_number', 'settlement_corre_amount',
            'jian_total_amount', 'add_total_amount',
            'deposit_one_agent_commission', 'deposit_two_agent_commission', 'deposit_three_agent_commission',
            'transfer_one_agent_commission', 'transfer_two_agent_commission', 'transfer_three_agent_commission',
            'settlement_one_agent_commission', 'settlement_two_agent_commission', 'settlement_three_agent_commission',
            'deposit_profit', 'transfer_profit', 'settlement_profit',
        ],
        'report_merchant_agents' => [
            'deposit_commission', 'transfer_commission', 'settlement_commission',
            'transfer_order_number_total', 'deposit_order_number_total', 'settlement_order_number_total',
            'deposit_order_total_amount', 'transfer_order_total_amount', 'settlement_order_total_amount',
            'jian_total_amount', 'add_total_amount',
        ],
        'report_users' => [
            'deposit_order_number_total', 'deposit_order_number_success', 'deposit_order_number_fail', 'deposit_order_number_overtime', 'deposit_order_number_swiping',
            'deposit_order_total_amount', 'deposit_order_total_fee', 'deposit_commission',
            'deposit_one_agent_commission', 'deposit_two_agent_commission', 'deposit_three_agent_commission', 'deposit_four_agent_commission', 'deposit_five_agent_commission', 'deposit_profit',
            'transfer_order_number_total', 'transfer_order_number_success', 'transfer_order_number_fail', 'transfer_order_total_amount', 'transfer_order_total_fee', 'transfer_commission',
            'transfer_one_agent_commission', 'transfer_two_agent_commission', 'transfer_three_agent_commission', 'transfer_four_agent_commission', 'transfer_five_agent_commission', 'transfer_profit',
            'commission_jian_total_amount', 'commission_add_total_amount', 'deposit_jian_total_amount', 'deposit_add_total_amount', 'transfer_jian_total_amount', 'transfer_add_total_amount',
            'settlement_order_number_total', 'settlement_order_number_success', 'settlement_order_number_fail', 'settlement_order_total_amount', 'settlement_order_total_fee', 'settlement_commission',
            'settlement_one_agent_commission', 'settlement_two_agent_commission', 'settlement_three_agent_commission', 'settlement_four_agent_commission', 'settlement_five_agent_commission', 'settlement_profit',
        ],
        'report_user_agents' => [
            'deposit_commission', 'transfer_commission',
            'transfer_order_number_total', 'deposit_order_number_total',
            'deposit_order_total_amount', 'transfer_order_total_amount',
            'jian_total_amount', 'add_total_amount',
        ],
        'report_channels' => [
            'deposit_order_number_total', 'deposit_order_number_success', 'deposit_order_number_fail', 'deposit_order_number_overtime', 'deposit_order_number_swiping',
            'deposit_order_total_amount', 'deposit_order_total_fee', 'deposit_profit',
            'transfer_order_number_total', 'transfer_order_number_success', 'transfer_order_number_fail', 'transfer_order_total_amount', 'transfer_order_total_fee', 'transfer_profit',
            'settlement_order_number_total', 'settlement_order_number_success', 'settlement_order_number_fail', 'settlement_order_total_amount', 'settlement_order_total_fee', 'settlement_profit',
        ],
        'report_payments' => [
            'deposit_order_number_total', 'deposit_order_number_success', 'deposit_order_number_fail', 'deposit_order_number_overtime', 'deposit_order_number_swiping',
            'deposit_order_total_amount', 'deposit_order_total_fee', 'deposit_profit',
        ],
        'report_currencies' => [
            'deposit_order_number_total', 'deposit_order_number_success', 'deposit_order_number_fail', 'deposit_order_number_overtime', 'deposit_order_number_swiping',
            'deposit_order_total_amount', 'deposit_order_total_fee', 'deposit_profit',
            'transfer_order_number_total', 'transfer_order_number_success', 'transfer_order_number_fail', 'transfer_order_total_amount', 'transfer_order_total_fee', 'transfer_profit',
            'settlement_order_number_total', 'settlement_order_number_success', 'settlement_order_number_fail', 'settlement_order_total_amount', 'settlement_order_total_fee', 'settlement_profit',
        ],
        'report_user_merchants' => [
            'deposit_order_number_total', 'deposit_order_number_success', 'deposit_order_number_fail', 'deposit_order_number_overtime', 'deposit_order_number_swiping',
            'deposit_order_total_amount', 'deposit_order_total_fee',
            'transfer_order_number_total', 'transfer_order_number_success', 'transfer_order_number_fail', 'transfer_order_total_amount', 'transfer_order_total_fee',
            'settlement_order_number_total', 'settlement_order_number_success', 'settlement_order_number_fail', 'settlement_order_total_amount', 'settlement_order_total_fee',
            'deposit_commission', 'transfer_commission', 'settlement_commission',
            'deposit_one_agent_commission', 'deposit_two_agent_commission', 'deposit_three_agent_commission', 'deposit_four_agent_commission', 'deposit_five_agent_commission',
            'transfer_one_agent_commission', 'transfer_two_agent_commission', 'transfer_three_agent_commission', 'transfer_four_agent_commission', 'transfer_five_agent_commission',
            'settlement_one_agent_commission', 'settlement_two_agent_commission', 'settlement_three_agent_commission', 'settlement_four_agent_commission', 'settlement_five_agent_commission',
            'commission_jian_total_amount', 'commission_add_total_amount', 'deposit_jian_total_amount', 'deposit_add_total_amount', 'transfer_jian_total_amount', 'transfer_add_total_amount',
            'deposit_profit', 'transfer_profit', 'settlement_profit',
        ],
        'report_channel_merchants' => [
            'deposit_order_number_total', 'deposit_order_number_success', 'deposit_order_number_fail', 'deposit_order_number_overtime', 'deposit_order_number_swiping',
            'deposit_order_total_amount', 'deposit_order_total_fee', 'deposit_profit',
            'transfer_order_number_total', 'transfer_order_number_success', 'transfer_order_number_fail', 'transfer_order_total_amount', 'transfer_order_total_fee', 'transfer_profit',
            'settlement_order_number_total', 'settlement_order_number_success', 'settlement_order_number_fail', 'settlement_order_total_amount', 'settlement_order_total_fee', 'settlement_profit',
        ],
        'report_payment_merchants' => [
            'deposit_order_number_total', 'deposit_order_number_success', 'deposit_order_number_fail', 'deposit_order_number_overtime', 'deposit_order_number_swiping',
            'deposit_order_total_amount', 'deposit_order_total_fee', 'deposit_profit',
        ],
        'report_currency_merchants' => [
            'deposit_order_number_total', 'deposit_order_number_success', 'deposit_order_number_fail', 'deposit_order_number_overtime', 'deposit_order_number_swiping',
            'deposit_order_total_amount', 'deposit_order_total_fee', 'deposit_profit',
            'transfer_order_number_total', 'transfer_order_number_success', 'transfer_order_number_fail', 'transfer_order_total_amount', 'transfer_order_total_fee', 'transfer_profit',
            'settlement_order_number_total', 'settlement_order_number_success', 'settlement_order_number_fail', 'settlement_order_total_amount', 'settlement_order_total_fee', 'settlement_profit',
        ],
        'report_user_banks' => [
            'deposit_order_number_total', 'deposit_order_number_success', 'deposit_order_number_fail', 'deposit_order_number_overtime',
            'deposit_order_total_amount', 'deposit_order_total_fee',
        ],
    ];

    public function build(string $date, array $tables, string $reportBatchNo = ''): void
    {
        $this->init($date);
        $this->collectOrderStats();
        $this->collectBalanceLogs();
        $this->applyUserMerchantBalanceLogs();
        $this->ensureActiveMerchantReportRows($tables);
        $this->ensureChannelReportRows($tables);
        $this->writeStats($tables, $this->stats);
    }

    public function buildMerchantOrderStats(string $date, int $mid, ?callable $heartbeat = null): array
    {
        $this->init($date);
        $this->activeMerchantIds = [$mid];
        $this->heartbeat = $heartbeat;
        $this->collectOrderStats();
        $this->ensureMerchantReportRow($mid);

        return $this->stats;
    }

    public function finalizeMerchantStats(string $date, array $tables, array $merchantStats): void
    {
        $this->init($date);
        $this->stats = $this->mergeStats($merchantStats);
        $this->collectBalanceLogs();
        $this->applyUserMerchantBalanceLogs();
        $this->ensureActiveMerchantReportRows($tables);
        $this->ensureChannelReportRows($tables);
        $this->writeStats($tables, $this->stats);
    }

    private function ensureActiveMerchantReportRows(array $tables): void
    {
        if (!in_array('report_merchants', $tables, true)) {
            return;
        }

        foreach ($this->activeMerchantIds as $mid) {
            $this->ensureMerchantReportRow((int) $mid);
        }
    }

    private function ensureMerchantReportRow(int $mid): void
    {
        if ($mid <= 0) {
            return;
        }

        $this->addStat('report_merchants', ['mid' => $mid], []);
    }

    private function ensureChannelReportRows(array $tables): void
    {
        if (!in_array('report_channels', $tables, true)) {
            return;
        }

        DB::table('channels')
            ->where('created_at', '<=', $this->endTime)
            ->orderBy('id')
            ->pluck('id')
            ->each(function ($channelId) {
                $this->addStat('report_channels', ['cid' => (int) $channelId], []);
            });
    }

    private function init(string $date): void
    {
        $this->date = $date;
        $this->startTime = $date . ' 00:00:00';
        $this->endTime = $date . ' 23:59:59';
        $this->startTimestamp = strtotime($this->startTime);
        $this->endTimestamp = strtotime($date . ' +1 day');
        $this->merchantUsersTable = config('merchant-admin.database.users_table', 'merchant_users');
        $this->activeMerchantIds = DB::table($this->merchantUsersTable)
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->map(fn ($id) => intval($id))
            ->filter()
            ->values()
            ->all();
        $this->stats = [];
        $this->userBalanceLogStats = [];
        $this->heartbeat = null;
    }

    private function collectDepositCreatedOrders(): void
    {
        if (empty($this->activeMerchantIds)) {
            return;
        }

        foreach ($this->dateCursorQuery('deposit_orders', 'created_at', [
            'id', 'mid', 'user_id', 'user_bank_id', 'channel_id', 'payment_id', 'currency_id', 'status', 'actual_amount', 'created_at',
        ], fn ($query) => $query->whereIn('mid', $this->activeMerchantIds)) as $orders) {
            foreach ($orders as $order) {
                $this->addDepositCreatedOrder($order);
            }
        }
    }

    private function collectDepositSuccessOrders(): void
    {
        if (empty($this->activeMerchantIds)) {
            return;
        }

        foreach ($this->timestampCursorQuery('deposit_orders', 'success_time', [
            'id', 'mid', 'user_id', 'user_bank_id', 'channel_id', 'payment_id', 'currency_id', 'status',
            'actual_amount', 'merchant_fee', 'merchant_extra_fee', 'profit', 'user_commission',
            'user_agent1_id', 'user_agent2_id', 'user_agent3_id', 'user_agent4_id', 'user_agent5_id',
            'user_agent1_commission', 'user_agent2_commission', 'user_agent3_commission', 'user_agent4_commission', 'user_agent5_commission',
            'merchant_agent1_id', 'merchant_agent2_id', 'merchant_agent3_id',
            'merchant_agent1_commission', 'merchant_agent2_commission', 'merchant_agent3_commission',
            'success_time',
        ], fn ($query) => $query->where('status', 5)->whereIn('mid', $this->activeMerchantIds)) as $orders) {
            foreach ($orders as $order) {
                $this->addDepositSuccessOrder($order);
            }
        }
    }

    private function collectTransferCreatedOrders(int $type, string $prefix): void
    {
        if (empty($this->activeMerchantIds)) {
            return;
        }

        foreach ($this->dateCursorQuery('transfer_orders', 'created_at', [
            'id', 'mid', 'user_id', 'channel_id', 'currency_id', 'status', 'type', 'actual_amount', 'created_at',
        ], fn ($query) => $query->where('type', $type)->whereIn('mid', $this->activeMerchantIds)) as $orders) {
            foreach ($orders as $order) {
                $this->addTransferCreatedOrder($order, $prefix);
            }
        }
    }

    private function collectTransferSuccessOrders(int $type, string $prefix): void
    {
        if (empty($this->activeMerchantIds)) {
            return;
        }

        foreach ($this->timestampCursorQuery('transfer_orders', 'success_time', [
            'id', 'mid', 'user_id', 'channel_id', 'currency_id', 'status', 'type',
            'actual_amount', 'merchant_fee', 'merchant_extra_fee', 'profit', 'user_commission',
            'user_agent1_id', 'user_agent2_id', 'user_agent3_id', 'user_agent4_id', 'user_agent5_id',
            'user_agent1_commission', 'user_agent2_commission', 'user_agent3_commission', 'user_agent4_commission', 'user_agent5_commission',
            'merchant_agent1_id', 'merchant_agent2_id', 'merchant_agent3_id',
            'merchant_agent1_commission', 'merchant_agent2_commission', 'merchant_agent3_commission',
            'success_time',
        ], fn ($query) => $query->where('type', $type)->where('status', 4)->whereIn('mid', $this->activeMerchantIds)) as $orders) {
            foreach ($orders as $order) {
                $this->addTransferSuccessOrder($order, $prefix);
            }
        }
    }

    private function collectOrderStats(): void
    {
        $this->collectDepositCreatedOrders();
        $this->collectDepositSuccessOrders();
        $this->collectTransferCreatedOrders(0, 'transfer');
        $this->collectTransferSuccessOrders(0, 'transfer');
        $this->collectTransferCreatedOrders(1, 'settlement');
        $this->collectTransferSuccessOrders(1, 'settlement');
    }

    private function collectBalanceLogs(): void
    {
        foreach ($this->dateCursorQuery('merchant_balance_logs', 'created_at', [
            'id', 'mid', 'amount', 'type', 'created_at',
        ], fn ($query) => $query->whereIn('type', [2, 5, 6, 9, 10, 11, 12, 15])) as $logs) {
            foreach ($logs as $log) {
                $this->addMerchantBalanceLog($log);
            }
        }

        DB::table('agent_balance_logs')
            ->whereBetween('created_at', [$this->startTime, $this->endTime])
            ->whereIn('type', [3, 4])
            ->orderBy('id')
            ->chunkById(self::CHUNK_SIZE, function (Collection $logs) {
                foreach ($logs as $log) {
                    $field = intval($log->type) === 4 ? 'add_total_amount' : 'jian_total_amount';
                    $this->addStat('report_merchant_agents', ['aid' => (int) $log->agent_id], [$field => abs((float) $log->amount)]);
                }
            });

        DB::table('user_balance_logs')
            ->whereBetween('created_at', [$this->startTime, $this->endTime])
            ->whereIn('type', [2, 3, 4, 5, 6, 8, 9])
            ->orderBy('id')
            ->chunkById(self::CHUNK_SIZE, function (Collection $logs) {
                foreach ($logs as $log) {
                    $this->addUserBalanceLog($log);
                }
            });
    }

    private function addDepositCreatedOrder(object $order): void
    {
        $values = [
            'deposit_order_number_total' => 1,
            'deposit_order_number_fail' => intval($order->status) === 6 ? 1 : 0,
            'deposit_order_number_overtime' => intval($order->status) === 4 ? 1 : 0,
            'deposit_order_number_swiping' => intval($order->status) === 2 ? 1 : 0,
        ];
        $merchantValues = array_merge($values, intval($order->status) === 5 ? [
            'deposit_order_number_success' => 1,
            'deposit_order_total_amount' => (float) $order->actual_amount,
        ] : []);

        $this->addStat('report_days', [], $values);
        $this->addStat('report_merchants', ['mid' => (int) $order->mid], $merchantValues);
        $this->addUserOrderStats($order, $values);
        $this->addDepositDimensionStats($order, $values);
    }

    private function addDepositSuccessOrder(object $order): void
    {
        $fee = (float) $order->merchant_fee + (float) $order->merchant_extra_fee;
        $values = [
            'deposit_order_number_success' => 1,
            'deposit_order_total_amount' => (float) $order->actual_amount,
            'deposit_order_total_fee' => $fee,
            'deposit_profit' => (float) $order->profit,
        ];
        $merchantValues = [
            'deposit_created_success_number' => 1,
            'deposit_created_success_amount' => (float) $order->actual_amount,
            'deposit_order_total_fee' => $fee,
            'deposit_profit' => (float) $order->profit,
            'deposit_one_agent_commission' => (float) $order->merchant_agent1_commission,
            'deposit_two_agent_commission' => (float) $order->merchant_agent2_commission,
            'deposit_three_agent_commission' => (float) $order->merchant_agent3_commission,
        ];
        $userValues = array_merge($values, [
            'deposit_commission' => (float) $order->user_commission,
            'deposit_one_agent_commission' => (float) $order->user_agent1_commission,
            'deposit_two_agent_commission' => (float) $order->user_agent2_commission,
            'deposit_three_agent_commission' => (float) $order->user_agent3_commission,
            'deposit_four_agent_commission' => (float) $order->user_agent4_commission,
            'deposit_five_agent_commission' => (float) $order->user_agent5_commission,
        ]);

        $this->addStat('report_days', [], ['deposit_order_number_success' => 1]);
        $this->addStat('report_merchants', ['mid' => (int) $order->mid], $merchantValues);
        $this->addUserOrderStats($order, $userValues);
        $this->addDepositDimensionStats($order, $values);
        $this->addMerchantAgentStats($order, 'deposit', 3);
        $this->addUserAgentStats($order, 'deposit', 5);
    }

    private function addTransferCreatedOrder(object $order, string $prefix): void
    {
        $values = [
            "{$prefix}_order_number_total" => 1,
            "{$prefix}_order_number_fail" => intval($order->status) === 5 ? 1 : 0,
        ];
        $merchantValues = array_merge($values, intval($order->status) === 4 ? [
            "{$prefix}_order_number_success" => 1,
            "{$prefix}_order_total_amount" => (float) $order->actual_amount,
        ] : []);

        $this->addStat('report_days', [], $values);
        $this->addStat('report_merchants', ['mid' => (int) $order->mid], $merchantValues);
        $this->addUserOrderStats($order, $values);
        $this->addTransferDimensionStats($order, $prefix, $values);
    }

    private function addTransferSuccessOrder(object $order, string $prefix): void
    {
        $fee = (float) $order->merchant_fee + (float) $order->merchant_extra_fee;
        $values = [
            "{$prefix}_order_number_success" => 1,
            "{$prefix}_order_total_amount" => (float) $order->actual_amount,
            "{$prefix}_order_total_fee" => $fee,
            "{$prefix}_profit" => (float) $order->profit,
        ];
        $merchantValues = [
            "{$prefix}_created_success_number" => 1,
            "{$prefix}_created_success_amount" => (float) $order->actual_amount,
            "{$prefix}_order_total_fee" => $fee,
            "{$prefix}_profit" => (float) $order->profit,
            "{$prefix}_one_agent_commission" => (float) $order->merchant_agent1_commission,
            "{$prefix}_two_agent_commission" => (float) $order->merchant_agent2_commission,
            "{$prefix}_three_agent_commission" => (float) $order->merchant_agent3_commission,
        ];
        $userValues = array_merge($values, [
            "{$prefix}_commission" => (float) $order->user_commission,
            "{$prefix}_one_agent_commission" => (float) $order->user_agent1_commission,
            "{$prefix}_two_agent_commission" => (float) $order->user_agent2_commission,
            "{$prefix}_three_agent_commission" => (float) $order->user_agent3_commission,
            "{$prefix}_four_agent_commission" => (float) $order->user_agent4_commission,
            "{$prefix}_five_agent_commission" => (float) $order->user_agent5_commission,
        ]);

        $this->addStat('report_days', [], ["{$prefix}_order_number_success" => 1]);
        $this->addStat('report_merchants', ['mid' => (int) $order->mid], $merchantValues);
        $this->addUserOrderStats($order, $userValues);
        $this->addTransferDimensionStats($order, $prefix, $values);
        $this->addMerchantAgentStats($order, $prefix, 3);
        if ($prefix === 'transfer') {
            $this->addUserAgentStats($order, $prefix, 5);
        }
    }

    private function addUserOrderStats(object $order, array $values): void
    {
        $uid = (int) $order->user_id;
        if ($uid <= 0) {
            return;
        }

        $this->addStat('report_users', ['uid' => $uid], $values);
        $this->addStat('report_user_merchants', ['mid' => (int) $order->mid, 'uid' => $uid], $values);
    }

    private function addDepositDimensionStats(object $order, array $values): void
    {
        $this->addChannelStats($order, $values);
        $this->addCurrencyStats($order, $values);

        $paymentId = (int) $order->payment_id;
        if ($paymentId > 0) {
            $this->addStat('report_payments', ['pid' => $paymentId], $values);
            $this->addStat('report_payment_merchants', ['mid' => (int) $order->mid, 'pid' => $paymentId], $values);
        }

        $uid = (int) $order->user_id;
        $userBankId = (int) $order->user_bank_id;
        if ($uid > 0 && $userBankId > 0) {
            $this->addStat('report_user_banks', ['uid' => $uid, 'ubid' => $userBankId], $values);
        }
    }

    private function addTransferDimensionStats(object $order, string $prefix, array $values): void
    {
        $this->addChannelStats($order, $values);
        $this->addCurrencyStats($order, $values);
    }

    private function addChannelStats(object $order, array $values): void
    {
        $channelId = (int) $order->channel_id;
        if ($channelId <= 0) {
            return;
        }

        $this->addStat('report_channels', ['cid' => $channelId], $values);
        $this->addStat('report_channel_merchants', ['mid' => (int) $order->mid, 'cid' => $channelId], $values);
    }

    private function addCurrencyStats(object $order, array $values): void
    {
        $currencyId = (int) $order->currency_id;
        if ($currencyId <= 0) {
            return;
        }

        $this->addStat('report_currencies', ['cid' => $currencyId], $values);
        $this->addStat('report_currency_merchants', ['mid' => (int) $order->mid, 'cid' => $currencyId], $values);
    }

    private function addMerchantAgentStats(object $order, string $prefix, int $maxLevel): void
    {
        for ($level = 1; $level <= $maxLevel; $level++) {
            $agentId = (int) ($order->{"merchant_agent{$level}_id"} ?? 0);
            if ($agentId <= 0) {
                continue;
            }

            $this->addStat('report_merchant_agents', ['aid' => $agentId], [
                "{$prefix}_commission" => (float) ($order->{"merchant_agent{$level}_commission"} ?? 0),
                "{$prefix}_order_number_total" => 1,
                "{$prefix}_order_total_amount" => (float) $order->actual_amount,
            ]);
        }
    }

    private function addUserAgentStats(object $order, string $prefix, int $maxLevel): void
    {
        for ($level = 1; $level <= $maxLevel; $level++) {
            $agentId = (int) ($order->{"user_agent{$level}_id"} ?? 0);
            if ($agentId <= 0) {
                continue;
            }

            $this->addStat('report_user_agents', ['aid' => $agentId], [
                "{$prefix}_commission" => (float) ($order->{"user_agent{$level}_commission"} ?? 0),
                "{$prefix}_order_number_total" => 1,
                "{$prefix}_order_total_amount" => (float) $order->actual_amount,
            ]);
        }
    }

    private function addUserBalanceLog(object $log): void
    {
        $userId = (int) $log->user_id;
        $amount = abs((float) $log->amount);
        if ($userId <= 0) {
            return;
        }

        $map = [
            2 => 'commission_jian_total_amount',
            3 => 'commission_add_total_amount',
            5 => 'deposit_jian_total_amount',
            6 => 'deposit_add_total_amount',
            8 => 'transfer_jian_total_amount',
            9 => 'transfer_add_total_amount',
        ];
        $type = (int) $log->type;
        if (isset($map[$type])) {
            $this->addStat('report_users', ['uid' => $userId], [$map[$type] => $amount]);
            $this->userBalanceLogStats[$userId][$map[$type]] = $this->sum($this->userBalanceLogStats[$userId][$map[$type]] ?? 0, $amount);
        }

        if (in_array($type, [3, 4], true)) {
            $this->addStat('report_user_agents', ['aid' => $userId], [
                $type === 4 ? 'add_total_amount' : 'jian_total_amount' => $amount,
            ]);
        }
    }

    private function addMerchantBalanceLog(object $log): void
    {
        $mid = (int) $log->mid;
        $type = (int) $log->type;
        $amount = abs((float) $log->amount);
        $fields = [
            2 => ['transfer_deduct_number', 'transfer_deduct_amount'],
            5 => ['transfer_corre_number', 'transfer_corre_amount'],
            6 => ['settlement_deduct_number', 'settlement_deduct_amount'],
            9 => ['deposit_freeze_number', 'deposit_freeze_amount'],
            10 => ['deposit_unfreeze_number', 'deposit_unfreeze_amount'],
            15 => ['settlement_corre_number', 'settlement_corre_amount'],
        ];

        if (isset($fields[$type])) {
            [$numberField, $amountField] = $fields[$type];
            $this->addStat('report_merchants', ['mid' => $mid], [$numberField => 1, $amountField => $amount]);
            return;
        }

        if (in_array($type, [11, 12], true)) {
            $field = $type === 11 ? 'add_total_amount' : 'jian_total_amount';
            $this->addStat('report_merchants', ['mid' => $mid], [$field => $amount]);
        }
    }

    private function applyUserMerchantBalanceLogs(): void
    {
        if (empty($this->userBalanceLogStats) || empty($this->stats['report_user_merchants'])) {
            return;
        }

        foreach ($this->stats['report_user_merchants'] as &$row) {
            $uid = (int) $row['uid'];
            if (!isset($this->userBalanceLogStats[$uid])) {
                continue;
            }

            foreach ($this->userBalanceLogStats[$uid] as $field => $amount) {
                $row[$field] = $this->sum($row[$field] ?? 0, $amount);
            }
        }
        unset($row);
    }

    private function addStat(string $table, array $keys, array $values): void
    {
        if (!isset($this->tableMetrics[$table])) {
            return;
        }

        foreach ($keys as $value) {
            if ((int) $value <= 0) {
                return;
            }
        }

        $statKey = $this->statKey($keys);
        if (!isset($this->stats[$table][$statKey])) {
            $this->stats[$table][$statKey] = array_merge(['date_add' => $this->date], $keys, array_fill_keys($this->tableMetrics[$table], 0));
        }

        foreach ($values as $field => $value) {
            if (!in_array($field, $this->tableMetrics[$table], true)) {
                continue;
            }

            $this->stats[$table][$statKey][$field] = $this->sum($this->stats[$table][$statKey][$field] ?? 0, $value);
        }
    }

    private function insertTable(string $table): void
    {
        $rows = array_values($this->stats[$table] ?? []);
        if (empty($rows)) {
            return;
        }

        $now = now()->toDateTimeString();
        foreach (array_chunk($rows, 500) as $chunk) {
            $insertRows = [];
            foreach ($chunk as $row) {
                $insertRows[] = array_merge($row, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table($table)->insert($insertRows);
        }
    }

    private function dateCursorQuery(string $table, string $dateField, array $columns, ?callable $configure = null): \Generator
    {
        $lastDate = '';
        $lastId = 0;

        do {
            $query = DB::table($table)
                ->select($columns)
                ->where($dateField, '>=', $this->startTime)
                ->where($dateField, '<=', $this->endTime);
            if ($configure) {
                $configure($query);
            }

            if ($lastDate !== '') {
                $query->where(function ($query) use ($dateField, $lastDate, $lastId) {
                    $query->where($dateField, '>', $lastDate)
                        ->orWhere(function ($query) use ($dateField, $lastDate, $lastId) {
                            $query->where($dateField, $lastDate)->where('id', '>', $lastId);
                        });
                });
            }

            $rows = $query->orderBy($dateField)->orderBy('id')->limit(self::CHUNK_SIZE)->get();
            if ($rows->isEmpty()) {
                break;
            }

            yield $rows;
            $this->heartbeat();

            $last = $rows->last();
            $lastDate = (string) $last->{$dateField};
            $lastId = (int) $last->id;
        } while ($rows->count() === self::CHUNK_SIZE);
    }

    private function timestampCursorQuery(string $table, string $timeField, array $columns, ?callable $configure = null): \Generator
    {
        $lastTime = null;
        $lastId = 0;

        do {
            $query = DB::table($table)
                ->select($columns)
                ->where($timeField, '>=', $this->startTimestamp)
                ->where($timeField, '<', $this->endTimestamp);
            if ($configure) {
                $configure($query);
            }

            if ($lastTime !== null) {
                $query->where(function ($query) use ($timeField, $lastTime, $lastId) {
                    $query->where($timeField, '>', $lastTime)
                        ->orWhere(function ($query) use ($timeField, $lastTime, $lastId) {
                            $query->where($timeField, $lastTime)->where('id', '>', $lastId);
                        });
                });
            }

            $rows = $query->orderBy($timeField)->orderBy('id')->limit(self::CHUNK_SIZE)->get();
            if ($rows->isEmpty()) {
                break;
            }

            yield $rows;
            $this->heartbeat();

            $last = $rows->last();
            $lastTime = (int) $last->{$timeField};
            $lastId = (int) $last->id;
        } while ($rows->count() === self::CHUNK_SIZE);
    }

    private function statKey(array $keys): string
    {
        if (empty($keys)) {
            return '_';
        }

        return implode('|', array_map(fn ($value) => (string) $value, $keys));
    }

    private function sum($left, $right): float
    {
        return round((float) $left + (float) $right, 4);
    }

    private function heartbeat(): void
    {
        if (is_callable($this->heartbeat)) {
            call_user_func($this->heartbeat);
        }
    }

    private function writeStats(array $tables, array $stats): void
    {
        $tables = array_values(array_intersect(array_keys($this->tableKeys), $tables));
        if (empty($tables)) {
            return;
        }

        $this->stats = $stats;
        DB::transaction(function () use ($tables) {
            foreach ($tables as $table) {
                DB::table($table)->where('date_add', $this->date)->delete();
                $this->insertTable($table);
            }
        }, 1);
    }

    private function mergeStats(array $statsList): array
    {
        $merged = [];
        foreach ($statsList as $stats) {
            if (!is_array($stats)) {
                continue;
            }

            foreach ($stats as $table => $rows) {
                if (!is_array($rows)) {
                    continue;
                }

                foreach ($rows as $statKey => $row) {
                    if (!is_array($row)) {
                        continue;
                    }

                    if (!isset($merged[$table][$statKey])) {
                        $merged[$table][$statKey] = $row;
                        continue;
                    }

                    foreach ($this->tableMetrics[$table] ?? [] as $field) {
                        $merged[$table][$statKey][$field] = $this->sum($merged[$table][$statKey][$field] ?? 0, $row[$field] ?? 0);
                    }
                }
            }
        }

        return $merged;
    }
}
