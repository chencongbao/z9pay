<?php

namespace App\Console\Commands;

use DateTime;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use App\Services\Report\ReportPendingDateService;

class BatchUserReportRepairCommand extends Command
{
    protected $signature = 'report:repair-batch-user {date : 统计日期，格式 YYYY-MM-DD}';

    protected $description = '批量修复指定日期的金主报表统计';

    public function handle(): int
    {
        $date = strval($this->argument('date'));
        if (!$this->isValidDate($date)) {
            $this->error('输入的日期格式不正确，请使用 YYYY-MM-DD 格式');
            return 1;
        }

        App::make(ReportPendingDateService::class)->addDates([$date]);
        $this->info("批量金主报表已加入待重建队列，日期：{$date}");

        return 0;
    }

    private function isValidDate(string $date): bool
    {
        $dateObj = DateTime::createFromFormat('Y-m-d', $date);

        return $dateObj && $dateObj->format('Y-m-d') === $date;
    }
}
