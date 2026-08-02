<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Jobs\Report\HandleReportDayJob;
use App\Jobs\Report\HandleReportUserJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use App\Jobs\Report\HandleReportMerchantJob;
use App\Jobs\Report\HandleReportUserAgentJob;
use App\Services\Report\ReportRunStateService;
use App\Jobs\Report\HandleReportMerchantAgentJob;
use App\Jobs\Report\HandleReportOrderDimensionJob;
use App\Services\Cache\Merchant\GetMerchantListInfoService;

class ReportCommandTest extends TestCase
{
    public function test_invalid_days_do_not_start_report_or_dispatch_jobs(): void
    {
        foreach (['0', '-1', 'abc'] as $days) {
            $this->assertInvalidDaysDoesNotStart($days, '统计天数必须是正整数。');
        }
    }

    public function test_oversized_days_do_not_start_report_or_dispatch_jobs(): void
    {
        $this->assertInvalidDaysDoesNotStart('367', '统计天数不能超过 366 天。');
    }

    public function test_valid_days_keep_existing_dispatch_behavior(): void
    {
        Queue::fake();
        $this->clearRunningState();
        $merchantService = $this->fakeMerchantListService();
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        $this->artisan('report', ['days' => '1'])
            ->expectsOutput("统计日期：{$yesterday}")
            ->expectsOutputToContain('报表统计已启动，批次号：')
            ->assertExitCode(0);

        $this->assertSame(1, $merchantService->calls);
        $this->assertNotEmpty(Cache::get($this->stateService()->runningCacheKey()));
        $this->assertReportJobsPushedForDate($yesterday);

        $this->clearRunningState();
    }

    public function test_default_report_command_dispatches_yesterday_reports(): void
    {
        Queue::fake();
        $this->clearRunningState();
        $this->fakeMerchantListService();
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        $this->artisan('report')
            ->expectsOutput("统计日期：{$yesterday}")
            ->expectsOutputToContain('报表统计已启动，批次号：')
            ->assertExitCode(0);

        $this->assertReportJobsPushedForDate($yesterday);

        $this->clearRunningState();
    }

    public function test_report_reset_without_force_does_not_truncate_or_clear_running_state(): void
    {
        $this->clearRunningState();
        Cache::put($this->stateService()->runningCacheKey(), 'codex-running', now()->addHour());
        DB::shouldReceive('table')->never();

        $this->artisan('report', ['--reset' => true])
            ->expectsOutput('重置报表数据属于破坏性操作，请显式指定 --force。')
            ->assertExitCode(1);

        $this->assertSame('codex-running', Cache::get($this->stateService()->runningCacheKey()));
        $this->clearRunningState();
    }

    public function test_report_reset_refuses_when_report_is_running_even_with_force(): void
    {
        $this->clearRunningState();
        Cache::put($this->stateService()->runningCacheKey(), 'codex-running', now()->addHour());
        DB::shouldReceive('table')->never();

        $this->artisan('report', ['--reset' => true, '--force' => true])
            ->expectsOutput('报表统计正在执行中，禁止重置报表数据。请等待任务结束后再执行。')
            ->assertExitCode(1);

        $this->assertSame('codex-running', Cache::get($this->stateService()->runningCacheKey()));
        $this->clearRunningState();
    }

    public function test_report_reset_with_force_truncates_only_report_table_whitelist(): void
    {
        $this->clearRunningState();
        $tables = [
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
        $builder = new class {
            public int $truncateCalls = 0;

            public function truncate(): void
            {
                $this->truncateCalls++;
            }
        };

        foreach ($tables as $table) {
            DB::shouldReceive('table')->once()->with($table)->andReturn($builder);
        }

        $this->artisan('report', ['--reset' => true, '--force' => true])
            ->expectsOutput('即将清空报表表数量：13')
            ->expectsOutput('报表数据已重置，清空表数量：13')
            ->assertExitCode(0);

        $this->assertSame(13, $builder->truncateCalls);
        $this->assertFalse(Cache::has($this->stateService()->runningCacheKey()));
    }

    private function assertInvalidDaysDoesNotStart(string $days, string $message): void
    {
        Queue::fake();
        $this->clearRunningState();
        $merchantService = $this->fakeMerchantListService();

        $this->artisan('report', ['days' => $days])
            ->expectsOutput($message)
            ->assertExitCode(1);

        $this->assertSame(0, $merchantService->calls);
        $this->assertFalse(Cache::has($this->stateService()->runningCacheKey()));
        Queue::assertNothingPushed();
    }

    private function assertReportJobsPushedForDate(string $date): void
    {
        foreach ($this->reportJobClasses() as $job) {
            Queue::assertPushed($job, function ($queuedJob) use ($date) {
                return $queuedJob->date_add === $date;
            });
        }
    }

    private function reportJobClasses(): array
    {
        return [
            HandleReportDayJob::class,
            HandleReportMerchantJob::class,
            HandleReportMerchantAgentJob::class,
            HandleReportUserJob::class,
            HandleReportUserAgentJob::class,
            HandleReportOrderDimensionJob::class,
        ];
    }

    private function fakeMerchantListService(): object
    {
        $service = new class {
            public int $calls = 0;

            public function excute(): array
            {
                $this->calls++;

                return [
                    ['status' => 1],
                ];
            }
        };

        $this->app->instance(GetMerchantListInfoService::class, $service);

        return $service;
    }

    private function clearRunningState(): void
    {
        $service = $this->stateService();
        $batchNo = Cache::get($service->runningCacheKey());
        if ($batchNo) {
            Cache::forget("report:batch:{$batchNo}:total");
            Cache::forget("report:batch:{$batchNo}:done");
            Cache::forget("report:batch:{$batchNo}:failed");
            Cache::forget("report:batch:{$batchNo}:meta");
        }

        Cache::forget($service->runningCacheKey());
        Cache::forget('report:batch:latest');
    }

    private function stateService(): ReportRunStateService
    {
        return app(ReportRunStateService::class);
    }
}
