<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use App\Services\Report\ReportRunStateService;
use App\Services\Report\ReportPendingDateService;
use App\Services\Report\ReportDateShardDispatchService;

class ReportConsumePendingDatesCommand extends Command
{
    protected $signature = 'report:consume-pending-dates';

    protected $description = '消费订单状态变更产生的待重建报表日期';

    public function handle(): int
    {
        $pendingDateService = App::make(ReportPendingDateService::class);
        $date = $pendingDateService->nextDate();
        if (!$date) {
            $this->info('暂无待重建报表日期。');
            return 0;
        }

        $reportRunStateService = App::make(ReportRunStateService::class);
        if (Cache::has($reportRunStateService->runningCacheKey())) {
            $this->info("报表统计正在运行，待重建日期保留：{$date}");
            return 0;
        }

        $batchNo = App::make(ReportDateShardDispatchService::class)->startAndDispatch($date);
        if (!$batchNo) {
            $this->warn("待重建日期派发失败，日期保留：{$date}");
            return 1;
        }

        $pendingDateService->removeDate($date);
        $this->info("待重建报表日期已派发，日期：{$date}，批次号：{$batchNo}");

        return 0;
    }
}
