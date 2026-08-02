<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use App\Services\Report\ReportRunStateService;
use App\Services\Report\ReportDateShardDispatchService;

class ReportCommand extends Command
{
    private const MAX_REPORT_DAYS = 366;

    protected $signature = 'report
        {date? : 统计日期 YYYY-MM-DD，或兼容旧用法填写统计天数}
        {--date= : 指定统计日期 YYYY-MM-DD}
        {--reset}
        {--force}
        {--today}';

    protected $description = '系统统计';

    public function handle()
    {
        $reportRunStateService = App::make(ReportRunStateService::class);
        $batchNo = $this->makeBatchNo();
        $started = false;

        try {
            if ($this->option('reset')) {
                if (!$this->canResetReports($reportRunStateService)) {
                    return 1;
                }

                $this->truncateReportTables();
                $reportRunStateService->resetRunning();
                $this->info('报表数据已重置，清空表数量：' . count($this->reportTables()));
                return 0;
            }

            $reportDateShardDispatchService = App::make(ReportDateShardDispatchService::class);
            $merchantIds = $reportDateShardDispatchService->activeMerchantIds();
            if (empty($merchantIds)) {
                $this->warn('没有可统计的商户，已停止报表统计。');
                return 1;
            }

            $dates = $this->resolveReportDates();
            if (empty($dates)) {
                $this->warn('没有需要统计的日期，已停止报表统计。');
                return 1;
            }
            if (Cache::has($reportRunStateService->runningCacheKey())) {
                $this->warn('报表统计正在执行中，请勿重复启动。');
                return 1;
            }

            $total = count($dates) * (count($merchantIds) + 1);
            if (!$reportRunStateService->start($batchNo, $total, $dates, count($merchantIds))) {
                $this->warn('报表统计正在执行中，请勿重复启动。');
                return 1;
            }
            $started = true;

            foreach ($dates as $date) {
                $reportDateShardDispatchService->dispatchDate($batchNo, $date, $merchantIds);
            }

            $this->info('统计日期：' . implode('、', $dates));
            $this->info("报表统计已启动，批次号：{$batchNo}");
            $this->info('可执行 php artisan report-status 查看进度。');
            return 0;
        } catch (\Throwable $e) {
            if ($started) {
                $reportRunStateService->releaseRunning($batchNo, 'stopped');
            }
            throw $e;
        }
    }

    private function canResetReports(ReportRunStateService $reportRunStateService): bool
    {
        if (!$this->option('force')) {
            $this->error('重置报表数据属于破坏性操作，请显式指定 --force。');
            return false;
        }

        if (Cache::has($reportRunStateService->runningCacheKey())) {
            $this->error('报表统计正在执行中，禁止重置报表数据。请等待任务结束后再执行。');
            return false;
        }

        $this->warn('即将清空报表表数量：' . count($this->reportTables()));
        return true;
    }

    private function resolveReportDates(): array
    {
        $dateOption = trim((string)($this->option('date') ?: ''));
        $dateArgument = trim((string)($this->argument('date') ?: ''));
        if ($dateOption !== '') {
            return $this->normalizeDate($dateOption) ? [$dateOption] : [];
        }

        if ($dateArgument !== '' && !ctype_digit($dateArgument)) {
            return $this->normalizeDate($dateArgument) ? [$dateArgument] : [];
        }

        $days = $this->normalizeDaysArgument($dateArgument === '' ? '1' : $dateArgument);
        if ($days === null) {
            return [];
        }

        $startDay = $this->option('today') ? 0 : 1;
        $dates = [];
        for ($i = $days; $i >= $startDay; $i--) {
            $dates[] = date('Y-m-d', strtotime("-{$i} day"));
        }

        return $dates;
    }

    private function normalizeDate(string $date): bool
    {
        $dateTime = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$dateTime || $dateTime->format('Y-m-d') !== $date) {
            $this->warn('统计日期格式错误，请使用 YYYY-MM-DD。');
            return false;
        }

        if ($date > date('Y-m-d')) {
            $this->warn('统计日期不能大于今天。');
            return false;
        }

        return true;
    }

    private function normalizeDaysArgument(string $days): ?int
    {
        $days = trim($days);
        if ($days === '' || !ctype_digit($days) || (int)$days <= 0) {
            $this->warn('统计天数必须是正整数。');
            return null;
        }

        $days = (int)$days;
        if ($days > self::MAX_REPORT_DAYS) {
            $this->warn('统计天数不能超过 ' . self::MAX_REPORT_DAYS . ' 天。');
            return null;
        }

        return $days;
    }

    private function makeBatchNo(): string
    {
        return date('YmdHis') . '_' . substr(md5(uniqid('', true)), 0, 8);
    }

    private function truncateReportTables(): void
    {
        foreach ($this->reportTables() as $table) {
            DB::table($table)->truncate();
        }
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
