<?php

namespace App\Console\Commands;

use Throwable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetDatabase extends Command
{
    protected $signature = 'reset:database {--force : 跳过确认直接执行，仅非生产环境可用}';

    protected $description = '初始化数据库数据';

    private const TRUNCATE_TABLES = [
        'activity_log',
        'admin_operation_log',
        'agent_balance_logs',
        'agent_role_users',
        'agent_user_relations',
        'agent_users',
        'bill_logs',
        'bill_users',
        'bills',
        'black_contents',
        'channel_bank_codes',
        'channel_rates',
        'database_cleanup_states',
        'deposit_merchant_tongjis',
        'deposite_order_logs',
        'deposit_orders',
        'deposit_tongjis',
        'failed_jobs',
        'freeze_orders',
        'group_addresses',
        'ip_blacklists',
        'ip_country_ranges',
        'listening_addresses',
        'listening_tron_addresses',
        'merchant_avg_usdt_logs',
        'merchant_agent_reports',
        'merchant_balance_logs',
        'merchant_callback_logs',
        'merchant_channels',
        'merchant_day_balance_logs',
        'merchant_infos',
        'merchant_payments',
        'merchant_role_users',
        'merchant_telegram_admins',
        'merchant_trade_logs',
        'merchant_users',
        'ordernumbers',
        'personal_access_tokens',
        'report_channel_merchants',
        'report_channels',
        'report_currencies',
        'report_currency_merchants',
        'report_days',
        'report_merchant_agents',
        'report_merchants',
        'report_payment_merchants',
        'report_payments',
        'report_user_agents',
        'report_user_banks',
        'report_user_merchants',
        'report_users',
        'transfer_order_logs',
        'transfer_orders',
        'uagent_user_relations',
        'user_agent_reports',
        'user_balance_logs',
        'user_bank_action_logs',
        'user_bank_balance_logs',
        'user_banks',
        'user_day_balance_logs',
        'user_deposit_details',
        'user_groups',
        'user_relations',
        'user_trade_logs',
        'users',
        'websockets_statistics_entries',
    ];

    private const DELETE_RULES = [
        'channels' => ['id', '>', 1],
        'admin_roles' => ['id', '>', 6],
        'admin_role_menu' => ['role_id', '>', 6],
        'admin_role_permissions' => ['role_id', '>', 6],
        'admin_users' => ['id', '>', 11],
        'merchant_roles' => ['id', '>', 2],
        'merchant_role_menu' => ['role_id', '>', 2],
        'merchant_role_permissions' => ['role_id', '>', 2],
        'channel_accounts' => ['id', '>', 1],
    ];

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('生产环境禁止执行数据库初始化命令');
            return self::FAILURE;
        }

        if (!$this->option('force') && !$this->confirm('该命令会清空业务数据，仅保留部分基础配置，确认继续？')) {
            $this->warn('已取消数据库初始化');
            return self::SUCCESS;
        }

        try {
            $this->resetData();
        } catch (Throwable) {
            return self::FAILURE;
        }

        $this->info('数据库初始化完成');

        return self::SUCCESS;
    }

    private function resetData(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach (self::TRUNCATE_TABLES as $table) {
                $this->truncateIfExists($table);
            }

            foreach (self::DELETE_RULES as $table => $rule) {
                $this->deleteByRuleIfExists($table, $rule);
            }

            if (Schema::hasTable('admin_role_users')) {
                DB::table('admin_role_users')->whereNotIn('user_id', [1, 11])->delete();
            }
        } catch (Throwable $e) {
            $this->error('数据库初始化失败：' . $e->getMessage());
            throw $e;
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    private function truncateIfExists(string $table): void
    {
        if (!Schema::hasTable($table)) {
            $this->warn("表不存在，已跳过：{$table}");
            return;
        }

        DB::table($table)->truncate();
        $this->line("已清空：{$table}");
    }

    private function deleteByRuleIfExists(string $table, array $rule): void
    {
        if (!Schema::hasTable($table)) {
            $this->warn("表不存在，已跳过：{$table}");
            return;
        }

        [$column, $operator, $value] = $rule;
        $count = DB::table($table)->where($column, $operator, $value)->delete();
        $this->line("已清理：{$table}，数量：{$count}");
    }
}
