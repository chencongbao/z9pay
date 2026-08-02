<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\User\UserTodayStatsRebuildService;

class UserTodayStatsRebuildCommand extends Command
{
    protected $signature = 'user:rebuild-today-stats {--user-id= : 只重建指定金主/代理今日统计} {--user-bank-id= : 只重建指定收款卡今日统计}';

    protected $description = '重建金主和收款卡今日统计缓存字段';

    public function handle(): int
    {
        $userId = $this->positiveIntegerOption('user-id');
        $userBankId = $this->positiveIntegerOption('user-bank-id');
        if ($userId === false || $userBankId === false) {
            return self::FAILURE;
        }

        $result = app(UserTodayStatsRebuildService::class)->rebuild($userId, $userBankId);

        $this->info("今日统计缓存已重建，日期：{$result['date']}，金主记录：{$result['users']}，收款卡记录：{$result['user_banks']}，待付款金主记录：{$result['pending_deposit_users']}");

        return self::SUCCESS;
    }

    private function positiveIntegerOption(string $name): int|false|null
    {
        $value = $this->option($name);
        if ($value === null || $value === '') {
            return null;
        }

        $value = trim((string)$value);
        if (!preg_match('/^[1-9]\d*$/', $value)) {
            $this->error("--{$name} 必须是正整数。");
            return false;
        }

        return (int)$value;
    }
}
