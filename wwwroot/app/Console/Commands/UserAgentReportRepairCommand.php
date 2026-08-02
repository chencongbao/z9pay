<?php

namespace App\Console\Commands;

use DateTime;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use App\Services\Report\ReportPendingDateService;

class UserAgentReportRepairCommand extends Command
{
    protected $signature = 'report:repair-user-agent {user_id=0 : 金主代理ID} {date=0 : 统计日期，格式 YYYY-MM-DD}';

    protected $description = '修复指定日期的金主代理报表统计';

    public function handle(): int
    {
        $userId = $this->positiveIntegerArgument('user_id', '金主代理ID');
        $date = strval($this->argument('date'));

        if ($userId === null) {
            return 1;
        }

        if ($date === '' || $date === '0') {
            $this->error('请输入统计日期，格式：YYYY-MM-DD');
            return 1;
        }

        if (!$this->isValidDate($date)) {
            $this->error('输入的日期格式不正确，请使用 YYYY-MM-DD 格式');
            return 1;
        }

        if (!User::query()->where('is_agent', 1)->whereKey($userId)->exists()) {
            $this->error('金主代理不存在');
            return 1;
        }

        App::make(ReportPendingDateService::class)->addDates([$date]);
        $this->info("金主代理报表已加入待重建队列，金主代理ID：{$userId}，日期：{$date}");

        return 0;
    }

    private function isValidDate(string $date): bool
    {
        $dateObj = DateTime::createFromFormat('Y-m-d', $date);

        return $dateObj && $dateObj->format('Y-m-d') === $date;
    }

    private function positiveIntegerArgument(string $name, string $label): ?int
    {
        $value = trim((string)$this->argument($name));
        if (preg_match('/^[1-9]\d*$/', $value) !== 1) {
            $this->error("请输入正确的{$label}");
            return null;
        }

        return (int)$value;
    }
}
