<?php

namespace App\Services\Report;

use Illuminate\Support\Facades\DB;

class ReportDateBuildService
{
    private string $date;

    private string $startTime;

    private string $endTime;

    private int $startTimestamp;

    private int $endTimestamp;

    private string $merchantUsersTable;

    private array $activeMerchantIds = [];

    public function excute(string $date, string $reportBatchNo = ''): void
    {
        $this->chunkBuilder()->build($date, $this->reportTables(), $reportBatchNo);
    }

    public function buildMerchantOrderStats(string $date, int $mid, ?callable $heartbeat = null): array
    {
        return $this->chunkBuilder()->buildMerchantOrderStats($date, $mid, $heartbeat);
    }

    public function finalizeMerchantStats(string $date, array $merchantStats): void
    {
        $this->chunkBuilder()->finalizeMerchantStats($date, $this->reportTables(), $merchantStats);
    }

    public function buildMerchants(string $date): void
    {
        $this->chunkBuilder()->build($date, ['report_merchants']);
    }

    public function buildMerchantAgents(string $date): void
    {
        $this->chunkBuilder()->build($date, ['report_merchant_agents']);
    }

    public function buildUsers(string $date): void
    {
        $this->chunkBuilder()->build($date, ['report_users', 'report_user_merchants']);
    }

    public function buildUserAgents(string $date): void
    {
        $this->chunkBuilder()->build($date, ['report_user_agents']);
    }

    public function buildOrderDimensions(string $date): void
    {
        $this->chunkBuilder()->build($date, [
            'report_channels',
            'report_payments',
            'report_currencies',
            'report_user_banks',
            'report_channel_merchants',
            'report_payment_merchants',
            'report_currency_merchants',
        ]);
    }

    public function buildChannels(string $date): void
    {
        $this->chunkBuilder()->build($date, ['report_channels', 'report_channel_merchants']);
    }

    public function buildPayments(string $date): void
    {
        $this->chunkBuilder()->build($date, ['report_payments', 'report_payment_merchants']);
    }

    public function buildCurrencies(string $date): void
    {
        $this->chunkBuilder()->build($date, ['report_currencies', 'report_currency_merchants']);
    }

    public function buildUserMerchants(string $date): void
    {
        $this->chunkBuilder()->build($date, ['report_user_merchants']);
    }

    public function buildUserBanks(string $date): void
    {
        $this->chunkBuilder()->build($date, ['report_user_banks']);
    }

    private function initDate(string $date): void
    {
        $this->date = $date;
        $this->startTime = $date . ' 00:00:00';
        $this->endTime = $date . ' 23:59:59';
        $this->startTimestamp = strtotime($this->startTime);
        $this->endTimestamp = strtotime($date . ' +1 day');
        $this->merchantUsersTable = config('merchant-admin.database.users_table', 'merchant_users');
        $this->activeMerchantIds = DB::table($this->merchantUsersTable)->where('status', 1)->whereNull('deleted_at')->pluck('id')->map(fn ($id) => intval($id))->filter()->values()->all();
    }

    private function rebuildReportTables(string $date, array $tables, callable $callback): void
    {
        $this->initDate($date);

        // 每个维度只重建自己负责的报表表，避免多个 Job 互相覆盖或重复累加。
        DB::transaction(function () use ($tables, $callback) {
            $this->deleteReportTables($tables);
            $callback();
        }, 1);
    }

    private function deleteReportDate(): void
    {
        $this->deleteReportTables($this->reportTables());
    }

    private function deleteReportTables(array $tables): void
    {
        foreach ($tables as $table) {
            DB::table($table)->where('date_add', $this->date)->delete();
        }
    }

    private function rebuildMerchants(): void
    {
        $metrics = $this->merchantMetrics();
        $this->insertAggregated('report_merchants', ['mid'], $metrics, [
            $this->orderSource('deposit_orders', ['mid' => 'mid'], $this->depositCreatedDimensionMetrics()),
            $this->successOrderSource('deposit_orders', ['mid' => 'mid'], $this->depositSuccessMerchantMetrics(), 'status = 5'),
            $this->orderSource('transfer_orders', ['mid' => 'mid'], $this->transferCreatedDimensionMetrics(), 'type = 0'),
            $this->successOrderSource('transfer_orders', ['mid' => 'mid'], $this->transferSuccessMerchantMetrics(), 'type = 0 AND status = 4'),
            $this->orderSource('transfer_orders', ['mid' => 'mid'], $this->settlementCreatedDimensionMetrics(), 'type = 1'),
            $this->successOrderSource('transfer_orders', ['mid' => 'mid'], $this->settlementSuccessMerchantMetrics(), 'type = 1 AND status = 4'),
            $this->logSource('merchant_balance_logs', ['mid' => 'mid'], [
                'add_total_amount' => 'SUM(IF(type = 11, ABS(amount), 0))',
                'jian_total_amount' => 'SUM(IF(type = 12, ABS(amount), 0))',
            ], 'type IN (11, 12)'),
        ]);
    }

    public function buildDays(string $date): void
    {
        $this->chunkBuilder()->build($date, ['report_days']);
    }

    private function chunkBuilder(): ReportDateChunkBuildService
    {
        return app(ReportDateChunkBuildService::class);
    }

    private function rebuildMerchantAgents(): void
    {
        $metrics = $this->merchantAgentMetrics();
        $this->insertAggregated('report_merchant_agents', ['aid'], $metrics, [
            $this->multiAgentSource('deposit_orders', ['merchant_agent1_id', 'merchant_agent2_id', 'merchant_agent3_id'], ['merchant_agent1_commission', 'merchant_agent2_commission', 'merchant_agent3_commission'], 'deposit'),
            $this->multiAgentSource('transfer_orders', ['merchant_agent1_id', 'merchant_agent2_id', 'merchant_agent3_id'], ['merchant_agent1_commission', 'merchant_agent2_commission', 'merchant_agent3_commission'], 'transfer', 'type = 0'),
            $this->multiAgentSource('transfer_orders', ['merchant_agent1_id', 'merchant_agent2_id', 'merchant_agent3_id'], ['merchant_agent1_commission', 'merchant_agent2_commission', 'merchant_agent3_commission'], 'settlement', 'type = 1'),
            $this->logSource('agent_balance_logs', ['aid' => 'agent_id'], [
                'add_total_amount' => 'SUM(IF(type = 4, ABS(amount), 0))',
                'jian_total_amount' => 'SUM(IF(type = 3, ABS(amount), 0))',
            ], 'type IN (3, 4)'),
        ]);
    }

    private function rebuildUsers(): void
    {
        $metrics = $this->userMetrics();
        $this->insertAggregated('report_users', ['uid'], $metrics, [
            $this->userMerchantReportSource(),
            $this->logSource('user_balance_logs', ['uid' => 'user_id'], $this->userBalanceLogMetrics(), 'type IN (2, 3, 5, 6, 8, 9)'),
        ]);
    }

    private function rebuildUserAgents(): void
    {
        $metrics = $this->userAgentMetrics();
        $this->insertAggregated('report_user_agents', ['aid'], $metrics, [
            $this->multiAgentSource('deposit_orders', ['user_agent1_id', 'user_agent2_id', 'user_agent3_id', 'user_agent4_id', 'user_agent5_id'], ['user_agent1_commission', 'user_agent2_commission', 'user_agent3_commission', 'user_agent4_commission', 'user_agent5_commission'], 'deposit'),
            $this->multiAgentSource('transfer_orders', ['user_agent1_id', 'user_agent2_id', 'user_agent3_id', 'user_agent4_id', 'user_agent5_id'], ['user_agent1_commission', 'user_agent2_commission', 'user_agent3_commission', 'user_agent4_commission', 'user_agent5_commission'], 'transfer', 'type = 0'),
            $this->logSource('user_balance_logs', ['aid' => 'user_id'], [
                'add_total_amount' => 'SUM(IF(type = 4, ABS(amount), 0))',
                'jian_total_amount' => 'SUM(IF(type = 3, ABS(amount), 0))',
            ], 'type IN (3, 4)'),
        ]);
    }

    private function rebuildOrderDimensionsFromTempTables(): void
    {
        $this->createOrderTempTables();

        try {
            $this->rebuildChannelMerchantsFromTempTables();
            $this->rebuildChannels();
            $this->rebuildPaymentMerchantsFromTempTables();
            $this->rebuildPayments();
            $this->rebuildCurrencyMerchantsFromTempTables();
            $this->rebuildCurrencies();
            $this->rebuildUserBanksFromTempTables();
        } finally {
            $this->dropOrderTempTables();
        }
    }

    private function rebuildDays(): void
    {
        $metrics = $this->dayMetrics();
        $columns = array_merge(['date_add'], $metrics, ['created_at', 'updated_at']);
        $selects = array_merge(['? AS date_add'], array_map(fn ($field) => "COALESCE(SUM({$field}), 0) AS {$field}", $metrics), ['NOW() AS created_at', 'NOW() AS updated_at']);
        $sources = [
            $this->dayOrderSource('deposit_orders', $this->depositCreatedDimensionMetrics()),
            $this->daySuccessOrderSource('deposit_orders', $this->depositSuccessDayMetrics(), 'status = 5'),
            $this->dayOrderSource('transfer_orders', $this->transferCreatedDimensionMetrics(), 'type = 0'),
            $this->daySuccessOrderSource('transfer_orders', $this->transferSuccessDayMetrics(), 'type = 0 AND status = 4'),
            $this->dayOrderSource('transfer_orders', $this->settlementCreatedDimensionMetrics(), 'type = 1'),
            $this->daySuccessOrderSource('transfer_orders', $this->settlementSuccessDayMetrics(), 'type = 1 AND status = 4'),
        ];
        $bindings = [$this->date];
        foreach ($sources as $source) {
            $bindings = array_merge($bindings, $source['bindings']);
        }

        // 日报表按日期做总汇总，页面查询只读小报表，不再扫订单大表。
        DB::statement("INSERT INTO report_days (" . implode(', ', $columns) . ") SELECT " . implode(', ', $selects) . " FROM (" . implode("\nUNION ALL\n", array_column($sources, 'sql')) . ") report_source", $bindings);
    }

    private function rebuildChannels(): void
    {
        $this->insertSummaryFromMerchantReport('report_channels', 'report_channel_merchants', 'cid', $this->dimensionMetrics());
    }

    private function rebuildPayments(): void
    {
        $this->insertSummaryFromMerchantReport('report_payments', 'report_payment_merchants', 'pid', $this->paymentMetrics());
    }

    private function rebuildCurrencies(): void
    {
        $this->insertSummaryFromMerchantReport('report_currencies', 'report_currency_merchants', 'cid', $this->dimensionMetrics());
    }

    private function rebuildUserMerchants(): void
    {
        $metrics = $this->userMerchantMetrics();
        $this->insertAggregated('report_user_merchants', ['mid', 'uid'], $metrics, [
            $this->orderSource('deposit_orders', ['mid' => 'mid', 'uid' => 'user_id'], $this->depositCreatedDimensionMetrics(), 'user_id > 0'),
            $this->successOrderSource('deposit_orders', ['mid' => 'mid', 'uid' => 'user_id'], $this->depositSuccessUserMetrics(), 'status = 5 AND user_id > 0'),
            $this->orderSource('transfer_orders', ['mid' => 'mid', 'uid' => 'user_id'], $this->transferCreatedDimensionMetrics(), 'type = 0 AND user_id > 0'),
            $this->successOrderSource('transfer_orders', ['mid' => 'mid', 'uid' => 'user_id'], $this->transferSuccessUserMetrics(), 'type = 0 AND status = 4 AND user_id > 0'),
            $this->orderSource('transfer_orders', ['mid' => 'mid', 'uid' => 'user_id'], $this->settlementCreatedDimensionMetrics(), 'type = 1 AND user_id > 0'),
            $this->successOrderSource('transfer_orders', ['mid' => 'mid', 'uid' => 'user_id'], $this->settlementSuccessUserMetrics(), 'type = 1 AND status = 4 AND user_id > 0'),
        ]);

        $this->updateUserMerchantBalanceLogFields();
    }

    private function rebuildChannelMerchants(): void
    {
        $metrics = $this->dimensionMetrics();
        $this->insertAggregated('report_channel_merchants', ['mid', 'cid'], $metrics, [
            $this->orderSource('deposit_orders', ['mid' => 'mid', 'cid' => 'channel_id'], $this->depositCreatedDimensionMetrics(), 'channel_id > 0'),
            $this->successOrderSource('deposit_orders', ['mid' => 'mid', 'cid' => 'channel_id'], $this->depositSuccessDimensionMetrics(), 'status = 5 AND channel_id > 0'),
            $this->orderSource('transfer_orders', ['mid' => 'mid', 'cid' => 'channel_id'], $this->transferCreatedDimensionMetrics(), 'type = 0 AND channel_id > 0'),
            $this->successOrderSource('transfer_orders', ['mid' => 'mid', 'cid' => 'channel_id'], $this->transferSuccessDimensionMetrics(), 'type = 0 AND status = 4 AND channel_id > 0'),
            $this->orderSource('transfer_orders', ['mid' => 'mid', 'cid' => 'channel_id'], $this->settlementCreatedDimensionMetrics(), 'type = 1 AND channel_id > 0'),
            $this->successOrderSource('transfer_orders', ['mid' => 'mid', 'cid' => 'channel_id'], $this->settlementSuccessDimensionMetrics(), 'type = 1 AND status = 4 AND channel_id > 0'),
        ]);
    }

    private function rebuildPaymentMerchants(): void
    {
        $metrics = $this->paymentMetrics();
        $this->insertAggregated('report_payment_merchants', ['mid', 'pid'], $metrics, [
            $this->orderSource('deposit_orders', ['mid' => 'mid', 'pid' => 'payment_id'], $this->depositCreatedDimensionMetrics(), 'payment_id > 0'),
            $this->successOrderSource('deposit_orders', ['mid' => 'mid', 'pid' => 'payment_id'], $this->depositSuccessDimensionMetrics(), 'status = 5 AND payment_id > 0'),
        ]);
    }

    private function rebuildCurrencyMerchants(): void
    {
        $metrics = $this->dimensionMetrics();
        $this->insertAggregated('report_currency_merchants', ['mid', 'cid'], $metrics, [
            $this->orderSource('deposit_orders', ['mid' => 'mid', 'cid' => 'currency_id'], $this->depositCreatedDimensionMetrics(), 'currency_id > 0'),
            $this->successOrderSource('deposit_orders', ['mid' => 'mid', 'cid' => 'currency_id'], $this->depositSuccessDimensionMetrics(), 'status = 5 AND currency_id > 0'),
            $this->orderSource('transfer_orders', ['mid' => 'mid', 'cid' => 'currency_id'], $this->transferCreatedDimensionMetrics(), 'type = 0 AND currency_id > 0'),
            $this->successOrderSource('transfer_orders', ['mid' => 'mid', 'cid' => 'currency_id'], $this->transferSuccessDimensionMetrics(), 'type = 0 AND status = 4 AND currency_id > 0'),
            $this->orderSource('transfer_orders', ['mid' => 'mid', 'cid' => 'currency_id'], $this->settlementCreatedDimensionMetrics(), 'type = 1 AND currency_id > 0'),
            $this->successOrderSource('transfer_orders', ['mid' => 'mid', 'cid' => 'currency_id'], $this->settlementSuccessDimensionMetrics(), 'type = 1 AND status = 4 AND currency_id > 0'),
        ]);
    }

    private function rebuildUserBanks(): void
    {
        $metrics = $this->userBankMetrics();
        $this->insertAggregated('report_user_banks', ['uid', 'ubid'], $metrics, [
            $this->orderSource('deposit_orders', ['uid' => 'user_id', 'ubid' => 'user_bank_id'], $this->depositCreatedUserBankMetrics(), 'user_id > 0 AND user_bank_id > 0'),
            $this->successOrderSource('deposit_orders', ['uid' => 'user_id', 'ubid' => 'user_bank_id'], $this->depositSuccessUserBankMetrics(), 'status = 5 AND user_id > 0 AND user_bank_id > 0'),
        ]);
    }

    private function rebuildChannelMerchantsFromTempTables(): void
    {
        $metrics = $this->dimensionMetrics();
        $this->insertAggregated('report_channel_merchants', ['mid', 'cid'], $metrics, [
            $this->tempSource('tmp_report_deposit_created_orders', ['mid' => 'mid', 'cid' => 'channel_id'], $this->depositCreatedDimensionMetrics(), 'channel_id > 0'),
            $this->tempSource('tmp_report_deposit_success_orders', ['mid' => 'mid', 'cid' => 'channel_id'], $this->depositSuccessDimensionMetrics(), 'channel_id > 0'),
            $this->tempSource('tmp_report_transfer_created_orders', ['mid' => 'mid', 'cid' => 'channel_id'], $this->transferCreatedDimensionMetrics(), 'channel_id > 0'),
            $this->tempSource('tmp_report_transfer_success_orders', ['mid' => 'mid', 'cid' => 'channel_id'], $this->transferSuccessDimensionMetrics(), 'channel_id > 0'),
            $this->tempSource('tmp_report_settlement_created_orders', ['mid' => 'mid', 'cid' => 'channel_id'], $this->settlementCreatedDimensionMetrics(), 'channel_id > 0'),
            $this->tempSource('tmp_report_settlement_success_orders', ['mid' => 'mid', 'cid' => 'channel_id'], $this->settlementSuccessDimensionMetrics(), 'channel_id > 0'),
        ]);
    }

    private function rebuildPaymentMerchantsFromTempTables(): void
    {
        $metrics = $this->paymentMetrics();
        $this->insertAggregated('report_payment_merchants', ['mid', 'pid'], $metrics, [
            $this->tempSource('tmp_report_deposit_created_orders', ['mid' => 'mid', 'pid' => 'payment_id'], $this->depositCreatedDimensionMetrics(), 'payment_id > 0'),
            $this->tempSource('tmp_report_deposit_success_orders', ['mid' => 'mid', 'pid' => 'payment_id'], $this->depositSuccessDimensionMetrics(), 'payment_id > 0'),
        ]);
    }

    private function rebuildCurrencyMerchantsFromTempTables(): void
    {
        $metrics = $this->dimensionMetrics();
        $this->insertAggregated('report_currency_merchants', ['mid', 'cid'], $metrics, [
            $this->tempSource('tmp_report_deposit_created_orders', ['mid' => 'mid', 'cid' => 'currency_id'], $this->depositCreatedDimensionMetrics(), 'currency_id > 0'),
            $this->tempSource('tmp_report_deposit_success_orders', ['mid' => 'mid', 'cid' => 'currency_id'], $this->depositSuccessDimensionMetrics(), 'currency_id > 0'),
            $this->tempSource('tmp_report_transfer_created_orders', ['mid' => 'mid', 'cid' => 'currency_id'], $this->transferCreatedDimensionMetrics(), 'currency_id > 0'),
            $this->tempSource('tmp_report_transfer_success_orders', ['mid' => 'mid', 'cid' => 'currency_id'], $this->transferSuccessDimensionMetrics(), 'currency_id > 0'),
            $this->tempSource('tmp_report_settlement_created_orders', ['mid' => 'mid', 'cid' => 'currency_id'], $this->settlementCreatedDimensionMetrics(), 'currency_id > 0'),
            $this->tempSource('tmp_report_settlement_success_orders', ['mid' => 'mid', 'cid' => 'currency_id'], $this->settlementSuccessDimensionMetrics(), 'currency_id > 0'),
        ]);
    }

    private function rebuildUserBanksFromTempTables(): void
    {
        $metrics = $this->userBankMetrics();
        $this->insertAggregated('report_user_banks', ['uid', 'ubid'], $metrics, [
            $this->tempSource('tmp_report_deposit_created_orders', ['uid' => 'user_id', 'ubid' => 'user_bank_id'], $this->depositCreatedUserBankMetrics(), 'user_id > 0 AND user_bank_id > 0'),
            $this->tempSource('tmp_report_deposit_success_orders', ['uid' => 'user_id', 'ubid' => 'user_bank_id'], $this->depositSuccessUserBankMetrics(), 'user_id > 0 AND user_bank_id > 0'),
        ]);
    }

    private function insertAggregated(string $table, array $keys, array $metrics, array $sources): void
    {
        $sources = array_values(array_filter($sources));
        if (empty($sources)) {
            return;
        }

        $columns = array_merge(['date_add'], $keys, $metrics, ['created_at', 'updated_at']);
        $selects = array_merge(['date_add'], $keys, array_map(fn ($field) => "SUM({$field}) AS {$field}", $metrics), ['NOW() AS created_at', 'NOW() AS updated_at']);
        $unionSql = implode("\nUNION ALL\n", array_column($sources, 'sql'));
        $bindings = [];
        foreach ($sources as $source) {
            $bindings = array_merge($bindings, $source['bindings']);
        }

        DB::statement("INSERT INTO {$table} (" . implode(', ', $columns) . ") SELECT " . implode(', ', $selects) . " FROM ({$unionSql}) report_source GROUP BY date_add, " . implode(', ', $keys), $bindings);
    }

    private function insertSummaryFromMerchantReport(string $table, string $sourceTable, string $key, array $metrics): void
    {
        $columns = array_merge(['date_add', $key], $metrics, ['created_at', 'updated_at']);
        $selects = array_merge(['date_add', $key], array_map(fn ($field) => "SUM({$field}) AS {$field}", $metrics), ['NOW() AS created_at', 'NOW() AS updated_at']);

        // 总维度报表直接从“商户+维度”报表汇总，避免重复扫描订单大表。
        DB::statement("INSERT INTO {$table} (" . implode(', ', $columns) . ") SELECT " . implode(', ', $selects) . " FROM {$sourceTable} WHERE date_add = ? GROUP BY date_add, {$key}", [$this->date]);
    }

    private function userMerchantReportSource(): array
    {
        $metrics = [];
        foreach (array_diff($this->userMetrics(), array_keys($this->userBalanceLogMetrics())) as $field) {
            $metrics[$field] = "SUM({$field})";
        }

        // 金主报表复用“金主+商户”明细，余额流水仍单独汇总，避免跨商户重复累加。
        return $this->source('report_user_merchants', ['uid' => 'uid'], $metrics, 'date_add = ?', [$this->date]);
    }

    private function createOrderTempTables(): void
    {
        // 多个维度共用当天订单窄表；创建类指标按 created_at，成功金额/手续费/利润按 success_time。
        $this->createOrderCreatedTempTable('deposit_orders', 'tmp_report_deposit_created_orders', 'mid, user_id, user_bank_id, channel_id, payment_id, currency_id, status');
        $this->createOrderSuccessTempTable('deposit_orders', 'tmp_report_deposit_success_orders', 'mid, user_id, user_bank_id, channel_id, payment_id, currency_id, actual_amount, merchant_fee, merchant_extra_fee, profit', 'status = 5');
        $this->createOrderCreatedTempTable('transfer_orders', 'tmp_report_transfer_created_orders', 'mid, user_id, channel_id, currency_id, status', 'type = 0');
        $this->createOrderSuccessTempTable('transfer_orders', 'tmp_report_transfer_success_orders', 'mid, user_id, channel_id, currency_id, actual_amount, merchant_fee, merchant_extra_fee, profit', 'type = 0 AND status = 4');
        $this->createOrderCreatedTempTable('transfer_orders', 'tmp_report_settlement_created_orders', 'mid, user_id, channel_id, currency_id, status', 'type = 1');
        $this->createOrderSuccessTempTable('transfer_orders', 'tmp_report_settlement_success_orders', 'mid, user_id, channel_id, currency_id, actual_amount, merchant_fee, merchant_extra_fee, profit', 'type = 1 AND status = 4');
    }

    private function createOrderCreatedTempTable(string $sourceTable, string $tempTable, string $columns, string $extraWhere = ''): void
    {
        DB::statement("DROP TEMPORARY TABLE IF EXISTS {$tempTable}");
        [$merchantWhere, $merchantBindings] = $this->activeMerchantWhere($sourceTable);
        $where = "created_at >= ? AND created_at <= ? AND {$merchantWhere}";
        if ($extraWhere !== '') {
            $where .= " AND {$extraWhere}";
        }

        DB::statement("CREATE TEMPORARY TABLE {$tempTable} AS SELECT {$columns} FROM {$sourceTable} WHERE {$where}", array_merge([$this->startTime, $this->endTime], $merchantBindings));
    }

    private function createOrderSuccessTempTable(string $sourceTable, string $tempTable, string $columns, string $extraWhere = ''): void
    {
        DB::statement("DROP TEMPORARY TABLE IF EXISTS {$tempTable}");
        [$merchantWhere, $merchantBindings] = $this->activeMerchantWhere($sourceTable);
        $where = "success_time >= ? AND success_time < ? AND {$merchantWhere}";
        if ($extraWhere !== '') {
            $where .= " AND {$extraWhere}";
        }

        DB::statement("CREATE TEMPORARY TABLE {$tempTable} AS SELECT {$columns} FROM {$sourceTable} WHERE {$where}", array_merge([$this->startTimestamp, $this->endTimestamp], $merchantBindings));
    }

    private function dropOrderTempTables(): void
    {
        foreach ([
            'tmp_report_deposit_created_orders',
            'tmp_report_deposit_success_orders',
            'tmp_report_transfer_created_orders',
            'tmp_report_transfer_success_orders',
            'tmp_report_settlement_created_orders',
            'tmp_report_settlement_success_orders',
        ] as $table) {
            DB::statement("DROP TEMPORARY TABLE IF EXISTS {$table}");
        }
    }

    private function orderSource(string $table, array $keys, array $metrics, string $extraWhere = ''): array
    {
        [$merchantWhere, $merchantBindings] = $this->activeMerchantWhere($table);
        $where = "created_at >= ? AND created_at <= ? AND {$merchantWhere}";
        if ($extraWhere !== '') {
            $where .= " AND {$extraWhere}";
        }

        return $this->source($table, $keys, $metrics, $where, array_merge([$this->startTime, $this->endTime], $merchantBindings));
    }

    private function successOrderSource(string $table, array $keys, array $metrics, string $extraWhere = ''): array
    {
        [$merchantWhere, $merchantBindings] = $this->activeMerchantWhere($table);
        $where = "success_time >= ? AND success_time < ? AND {$merchantWhere}";
        if ($extraWhere !== '') {
            $where .= " AND {$extraWhere}";
        }

        return $this->source($table, $keys, $metrics, $where, array_merge([$this->startTimestamp, $this->endTimestamp], $merchantBindings));
    }

    private function tempSource(string $table, array $keys, array $metrics, string $extraWhere = ''): array
    {
        $where = $extraWhere !== '' ? $extraWhere : '1 = 1';
        return $this->source($table, $keys, $metrics, $where, []);
    }

    private function logSource(string $table, array $keys, array $metrics, string $extraWhere = ''): array
    {
        $where = 'created_at >= ? AND created_at <= ?';
        if ($extraWhere !== '') {
            $where .= " AND {$extraWhere}";
        }

        return $this->source($table, $keys, $metrics, $where, [$this->startTime, $this->endTime]);
    }

    private function dayOrderSource(string $table, array $enabledMetrics, string $extraWhere = ''): array
    {
        [$merchantWhere, $merchantBindings] = $this->activeMerchantWhere($table);
        $where = "created_at >= ? AND created_at <= ? AND {$merchantWhere}";
        if ($extraWhere !== '') {
            $where .= " AND {$extraWhere}";
        }

        $selects = [];
        foreach ($this->allDayMetrics($enabledMetrics) as $field => $expr) {
            $selects[] = "{$expr} AS {$field}";
        }

        return [
            'sql' => "SELECT " . implode(', ', $selects) . " FROM {$table} WHERE {$where}",
            'bindings' => array_merge([$this->startTime, $this->endTime], $merchantBindings),
        ];
    }

    private function daySuccessOrderSource(string $table, array $enabledMetrics, string $extraWhere = ''): array
    {
        [$merchantWhere, $merchantBindings] = $this->activeMerchantWhere($table);
        $where = "success_time >= ? AND success_time < ? AND {$merchantWhere}";
        if ($extraWhere !== '') {
            $where .= " AND {$extraWhere}";
        }

        $selects = [];
        foreach ($this->allDayMetrics($enabledMetrics) as $field => $expr) {
            $selects[] = "{$expr} AS {$field}";
        }

        return [
            'sql' => "SELECT " . implode(', ', $selects) . " FROM {$table} WHERE {$where}",
            'bindings' => array_merge([$this->startTimestamp, $this->endTimestamp], $merchantBindings),
        ];
    }

    private function multiAgentSource(string $table, array $agentFields, array $commissionFields, string $prefix, string $extraWhere = ''): array
    {
        $levels = [];
        foreach (array_keys($agentFields) as $index) {
            $levels[] = 'SELECT ' . ($index + 1) . ' n';
        }

        $aidCase = $this->caseByLevel('lv.n', $agentFields);
        $commissionCase = $this->caseByLevel('lv.n', $commissionFields);
        $successStatus = $table === 'deposit_orders' ? 5 : 4;
        [$merchantWhere, $merchantBindings] = $this->activeMerchantWhere($table);
        $where = "success_time >= ? AND success_time < ? AND status = {$successStatus} AND {$merchantWhere} AND {$aidCase} > 0";
        if ($extraWhere !== '') {
            $where .= " AND {$extraWhere}";
        }

        return $this->sourceWithJoin($table, ['aid' => $aidCase], [
            "{$prefix}_commission" => "SUM({$commissionCase})",
            "{$prefix}_order_number_total" => 'COUNT(*)',
            "{$prefix}_order_total_amount" => 'SUM(actual_amount)',
        ], 'JOIN (' . implode(' UNION ALL ', $levels) . ') lv', $where, array_merge([$this->startTimestamp, $this->endTimestamp], $merchantBindings));
    }

    private function activeMerchantWhere(string $table): array
    {
        if (empty($this->activeMerchantIds)) {
            return ['1 = 0', []];
        }

        return [$table . '.mid IN (' . implode(',', array_fill(0, count($this->activeMerchantIds), '?')) . ')', $this->activeMerchantIds];
    }

    private function caseByLevel(string $levelField, array $fields): string
    {
        $parts = [];
        foreach ($fields as $index => $field) {
            $parts[] = 'WHEN ' . ($index + 1) . " THEN {$field}";
        }

        return "CASE {$levelField} " . implode(' ', $parts) . ' END';
    }

    private function source(string $table, array $keys, array $metrics, string $where, array $bindings): array
    {
        return $this->sourceWithJoin($table, $keys, $metrics, '', $where, $bindings);
    }

    private function sourceWithJoin(string $table, array $keys, array $metrics, string $join, string $where, array $bindings): array
    {
        $keySelects = [];
        foreach ($keys as $alias => $expr) {
            $keySelects[] = "{$expr} AS {$alias}";
        }

        $metricSelects = [];
        foreach ($this->allMetrics($metrics) as $field => $expr) {
            $metricSelects[] = "{$expr} AS {$field}";
        }

        return [
            'sql' => "SELECT ? AS date_add, " . implode(', ', $keySelects) . ', ' . implode(', ', $metricSelects) . " FROM {$table} {$join} WHERE {$where} GROUP BY " . implode(', ', array_values($keys)),
            'bindings' => array_merge([$this->date], $bindings),
        ];
    }

    private function allMetrics(array $enabled): array
    {
        $metrics = [];
        foreach ($this->currentMetrics as $field) {
            $metrics[$field] = $enabled[$field] ?? '0';
        }

        return $metrics;
    }

    private function allDayMetrics(array $enabled): array
    {
        $metrics = [];
        foreach ($this->dayMetrics() as $field) {
            $metrics[$field] = $enabled[$field] ?? '0';
        }

        return $metrics;
    }

    private array $currentMetrics = [];

    private function merchantMetrics(): array
    {
        return $this->setMetrics([
            'deposit_order_number_total', 'deposit_order_number_success', 'deposit_order_number_fail', 'deposit_order_number_overtime', 'deposit_order_number_swiping',
            'deposit_order_total_amount', 'deposit_order_total_fee', 'deposit_one_agent_commission', 'deposit_two_agent_commission', 'deposit_three_agent_commission', 'deposit_profit',
            'transfer_order_number_total', 'transfer_order_number_success', 'transfer_order_number_fail', 'transfer_order_total_amount', 'transfer_order_total_fee',
            'transfer_one_agent_commission', 'transfer_two_agent_commission', 'transfer_three_agent_commission', 'transfer_profit',
            'settlement_order_number_total', 'settlement_order_number_success', 'settlement_order_number_fail', 'settlement_order_total_amount', 'settlement_order_total_fee',
            'settlement_one_agent_commission', 'settlement_two_agent_commission', 'settlement_three_agent_commission', 'settlement_profit',
            'add_total_amount', 'jian_total_amount',
        ]);
    }

    private function merchantAgentMetrics(): array
    {
        return $this->setMetrics([
            'deposit_commission', 'transfer_commission', 'settlement_commission',
            'deposit_order_number_total', 'transfer_order_number_total', 'settlement_order_number_total',
            'deposit_order_total_amount', 'transfer_order_total_amount', 'settlement_order_total_amount',
            'add_total_amount', 'jian_total_amount',
        ]);
    }

    private function userMetrics(): array
    {
        return $this->setMetrics([
            'deposit_order_number_total', 'deposit_order_number_success', 'deposit_order_number_fail', 'deposit_order_number_overtime', 'deposit_order_number_swiping',
            'deposit_order_total_amount', 'deposit_order_total_fee', 'deposit_commission', 'deposit_one_agent_commission', 'deposit_two_agent_commission',
            'deposit_three_agent_commission', 'deposit_four_agent_commission', 'deposit_five_agent_commission', 'deposit_profit',
            'transfer_order_number_total', 'transfer_order_number_success', 'transfer_order_number_fail', 'transfer_order_total_amount', 'transfer_order_total_fee',
            'transfer_commission', 'transfer_one_agent_commission', 'transfer_two_agent_commission', 'transfer_three_agent_commission',
            'transfer_four_agent_commission', 'transfer_five_agent_commission', 'transfer_profit',
            'settlement_order_number_total', 'settlement_order_number_success', 'settlement_order_number_fail', 'settlement_order_total_amount',
            'settlement_order_total_fee', 'settlement_commission', 'settlement_one_agent_commission', 'settlement_two_agent_commission',
            'settlement_three_agent_commission', 'settlement_four_agent_commission', 'settlement_five_agent_commission', 'settlement_profit',
            'commission_jian_total_amount', 'commission_add_total_amount', 'deposit_jian_total_amount', 'deposit_add_total_amount',
            'transfer_jian_total_amount', 'transfer_add_total_amount',
        ]);
    }

    private function userAgentMetrics(): array
    {
        return $this->setMetrics(['deposit_commission', 'transfer_commission', 'deposit_order_number_total', 'transfer_order_number_total', 'deposit_order_total_amount', 'transfer_order_total_amount', 'add_total_amount', 'jian_total_amount']);
    }

    private function dimensionMetrics(): array
    {
        return $this->setMetrics([
            'deposit_order_number_total', 'deposit_order_number_success', 'deposit_order_number_fail', 'deposit_order_number_overtime', 'deposit_order_number_swiping',
            'deposit_order_total_amount', 'deposit_order_total_fee', 'deposit_profit',
            'transfer_order_number_total', 'transfer_order_number_success', 'transfer_order_number_fail', 'transfer_order_total_amount', 'transfer_order_total_fee', 'transfer_profit',
            'settlement_order_number_total', 'settlement_order_number_success', 'settlement_order_number_fail', 'settlement_order_total_amount', 'settlement_order_total_fee', 'settlement_profit',
        ]);
    }

    private function dayMetrics(): array
    {
        return [
            'deposit_order_number_total', 'deposit_order_number_success', 'deposit_order_number_fail', 'deposit_order_number_overtime', 'deposit_order_number_swiping',
            'transfer_order_number_total', 'transfer_order_number_success', 'transfer_order_number_fail',
            'settlement_order_number_total', 'settlement_order_number_success', 'settlement_order_number_fail',
        ];
    }

    private function paymentMetrics(): array
    {
        return $this->setMetrics(['deposit_order_number_total', 'deposit_order_number_success', 'deposit_order_number_fail', 'deposit_order_number_overtime', 'deposit_order_number_swiping', 'deposit_order_total_amount', 'deposit_order_total_fee', 'deposit_profit']);
    }

    private function userMerchantMetrics(): array
    {
        return $this->userMetrics();
    }

    private function userBankMetrics(): array
    {
        return $this->setMetrics(['deposit_order_number_total', 'deposit_order_number_success', 'deposit_order_number_fail', 'deposit_order_number_overtime', 'deposit_order_total_amount', 'deposit_order_total_fee']);
    }

    private function setMetrics(array $metrics): array
    {
        $this->currentMetrics = $metrics;
        return $metrics;
    }

    private function depositCreatedDimensionMetrics(): array
    {
        return [
            'deposit_order_number_total' => 'COUNT(*)',
            'deposit_order_number_fail' => 'SUM(status = 6)',
            'deposit_order_number_overtime' => 'SUM(status = 4)',
            'deposit_order_number_swiping' => 'SUM(status = 2)',
        ];
    }

    private function depositSuccessDayMetrics(): array
    {
        return [
            'deposit_order_number_success' => 'COUNT(*)',
        ];
    }

    private function depositSuccessDimensionMetrics(): array
    {
        return array_merge($this->depositSuccessDayMetrics(), [
            'deposit_order_total_amount' => 'SUM(actual_amount)',
            'deposit_order_total_fee' => 'SUM(merchant_fee + merchant_extra_fee)',
            'deposit_profit' => 'SUM(profit)',
        ]);
    }

    private function depositSuccessMerchantMetrics(): array
    {
        return array_merge($this->depositSuccessDimensionMetrics(), [
            'deposit_one_agent_commission' => 'SUM(IF(merchant_agent1_id > 0, merchant_agent1_commission, 0))',
            'deposit_two_agent_commission' => 'SUM(IF(merchant_agent2_id > 0, merchant_agent2_commission, 0))',
            'deposit_three_agent_commission' => 'SUM(IF(merchant_agent3_id > 0, merchant_agent3_commission, 0))',
        ]);
    }

    private function depositSuccessUserMetrics(): array
    {
        return array_merge($this->depositSuccessDimensionMetrics(), [
            'deposit_commission' => 'SUM(user_commission)',
            'deposit_one_agent_commission' => 'SUM(IF(user_agent1_id > 0, user_agent1_commission, 0))',
            'deposit_two_agent_commission' => 'SUM(IF(user_agent2_id > 0, user_agent2_commission, 0))',
            'deposit_three_agent_commission' => 'SUM(IF(user_agent3_id > 0, user_agent3_commission, 0))',
            'deposit_four_agent_commission' => 'SUM(IF(user_agent4_id > 0, user_agent4_commission, 0))',
            'deposit_five_agent_commission' => 'SUM(IF(user_agent5_id > 0, user_agent5_commission, 0))',
        ]);
    }

    private function depositCreatedUserBankMetrics(): array
    {
        return [
            'deposit_order_number_total' => 'COUNT(*)',
            'deposit_order_number_fail' => 'SUM(status = 6)',
            'deposit_order_number_overtime' => 'SUM(status = 4)',
        ];
    }

    private function depositSuccessUserBankMetrics(): array
    {
        return [
            'deposit_order_number_success' => 'COUNT(*)',
            'deposit_order_total_amount' => 'SUM(actual_amount)',
            'deposit_order_total_fee' => 'SUM(merchant_fee + merchant_extra_fee)',
        ];
    }

    private function transferCreatedDimensionMetrics(): array
    {
        return [
            'transfer_order_number_total' => 'COUNT(*)',
            'transfer_order_number_fail' => 'SUM(status = 5)',
        ];
    }

    private function transferSuccessDayMetrics(): array
    {
        return [
            'transfer_order_number_success' => 'COUNT(*)',
        ];
    }

    private function transferSuccessDimensionMetrics(): array
    {
        return array_merge($this->transferSuccessDayMetrics(), [
            'transfer_order_total_amount' => 'SUM(actual_amount)',
            'transfer_order_total_fee' => 'SUM(merchant_fee + merchant_extra_fee)',
            'transfer_profit' => 'SUM(profit)',
        ]);
    }

    private function transferSuccessMerchantMetrics(): array
    {
        return array_merge($this->transferSuccessDimensionMetrics(), [
            'transfer_one_agent_commission' => 'SUM(IF(merchant_agent1_id > 0, merchant_agent1_commission, 0))',
            'transfer_two_agent_commission' => 'SUM(IF(merchant_agent2_id > 0, merchant_agent2_commission, 0))',
            'transfer_three_agent_commission' => 'SUM(IF(merchant_agent3_id > 0, merchant_agent3_commission, 0))',
        ]);
    }

    private function transferSuccessUserMetrics(): array
    {
        return array_merge($this->transferSuccessDimensionMetrics(), [
            'transfer_commission' => 'SUM(user_commission)',
            'transfer_one_agent_commission' => 'SUM(IF(user_agent1_id > 0, user_agent1_commission, 0))',
            'transfer_two_agent_commission' => 'SUM(IF(user_agent2_id > 0, user_agent2_commission, 0))',
            'transfer_three_agent_commission' => 'SUM(IF(user_agent3_id > 0, user_agent3_commission, 0))',
            'transfer_four_agent_commission' => 'SUM(IF(user_agent4_id > 0, user_agent4_commission, 0))',
            'transfer_five_agent_commission' => 'SUM(IF(user_agent5_id > 0, user_agent5_commission, 0))',
        ]);
    }

    private function settlementCreatedDimensionMetrics(): array
    {
        return [
            'settlement_order_number_total' => 'COUNT(*)',
            'settlement_order_number_fail' => 'SUM(status = 5)',
        ];
    }

    private function settlementSuccessDayMetrics(): array
    {
        return [
            'settlement_order_number_success' => 'COUNT(*)',
        ];
    }

    private function settlementSuccessDimensionMetrics(): array
    {
        return array_merge($this->settlementSuccessDayMetrics(), [
            'settlement_order_total_amount' => 'SUM(actual_amount)',
            'settlement_order_total_fee' => 'SUM(merchant_fee + merchant_extra_fee)',
            'settlement_profit' => 'SUM(profit)',
        ]);
    }

    private function settlementSuccessMerchantMetrics(): array
    {
        return array_merge($this->settlementSuccessDimensionMetrics(), [
            'settlement_one_agent_commission' => 'SUM(IF(merchant_agent1_id > 0, merchant_agent1_commission, 0))',
            'settlement_two_agent_commission' => 'SUM(IF(merchant_agent2_id > 0, merchant_agent2_commission, 0))',
            'settlement_three_agent_commission' => 'SUM(IF(merchant_agent3_id > 0, merchant_agent3_commission, 0))',
        ]);
    }

    private function settlementSuccessUserMetrics(): array
    {
        return array_merge($this->settlementSuccessDimensionMetrics(), [
            'settlement_commission' => 'SUM(user_commission)',
            'settlement_one_agent_commission' => 'SUM(IF(user_agent1_id > 0, user_agent1_commission, 0))',
            'settlement_two_agent_commission' => 'SUM(IF(user_agent2_id > 0, user_agent2_commission, 0))',
            'settlement_three_agent_commission' => 'SUM(IF(user_agent3_id > 0, user_agent3_commission, 0))',
            'settlement_four_agent_commission' => 'SUM(IF(user_agent4_id > 0, user_agent4_commission, 0))',
            'settlement_five_agent_commission' => 'SUM(IF(user_agent5_id > 0, user_agent5_commission, 0))',
        ]);
    }

    private function userBalanceLogMetrics(): array
    {
        return [
            'commission_jian_total_amount' => 'SUM(IF(type = 2, ABS(amount), 0))',
            'commission_add_total_amount' => 'SUM(IF(type = 3, ABS(amount), 0))',
            'deposit_jian_total_amount' => 'SUM(IF(type = 5, ABS(amount), 0))',
            'deposit_add_total_amount' => 'SUM(IF(type = 6, ABS(amount), 0))',
            'transfer_jian_total_amount' => 'SUM(IF(type = 8, ABS(amount), 0))',
            'transfer_add_total_amount' => 'SUM(IF(type = 9, ABS(amount), 0))',
        ];
    }

    private function updateUserMerchantBalanceLogFields(): void
    {
        DB::statement("
            UPDATE report_user_merchants rum
            JOIN (
                SELECT user_id,
                    SUM(IF(type = 2, ABS(amount), 0)) AS commission_jian_total_amount,
                    SUM(IF(type = 3, ABS(amount), 0)) AS commission_add_total_amount,
                    SUM(IF(type = 5, ABS(amount), 0)) AS deposit_jian_total_amount,
                    SUM(IF(type = 6, ABS(amount), 0)) AS deposit_add_total_amount,
                    SUM(IF(type = 8, ABS(amount), 0)) AS transfer_jian_total_amount,
                    SUM(IF(type = 9, ABS(amount), 0)) AS transfer_add_total_amount
                FROM user_balance_logs
                WHERE created_at >= ? AND created_at <= ? AND type IN (2, 3, 5, 6, 8, 9)
                GROUP BY user_id
            ) logs ON logs.user_id = rum.uid
            SET rum.commission_jian_total_amount = logs.commission_jian_total_amount,
                rum.commission_add_total_amount = logs.commission_add_total_amount,
                rum.deposit_jian_total_amount = logs.deposit_jian_total_amount,
                rum.deposit_add_total_amount = logs.deposit_add_total_amount,
                rum.transfer_jian_total_amount = logs.transfer_jian_total_amount,
                rum.transfer_add_total_amount = logs.transfer_add_total_amount
            WHERE rum.date_add = ?
        ", [$this->startTime, $this->endTime, $this->date]);
    }

    private function reportTables(): array
    {
        return [
            'report_days',
            'report_merchants',
            'report_merchant_agents',
            'report_users',
            'report_user_agents',
            'report_channels',
            'report_payments',
            'report_currencies',
            'report_user_merchants',
            'report_channel_merchants',
            'report_payment_merchants',
            'report_currency_merchants',
            'report_user_banks',
        ];
    }
}
