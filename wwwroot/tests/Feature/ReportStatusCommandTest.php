<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Cache;

class ReportStatusCommandTest extends TestCase
{
    public function test_missing_batch_reports_empty_status_without_exception(): void
    {
        $batchNo = 'codex_missing_' . uniqid('', true);
        $this->forgetBatch($batchNo);

        $this->artisan('report-status', ['batch_no' => $batchNo])
            ->expectsOutput('暂无报表运行记录。')
            ->assertExitCode(0);
    }

    public function test_old_cache_without_optional_meta_fields_reports_status_without_exception(): void
    {
        $batchNo = 'codex_old_meta_' . uniqid('', true);
        $this->forgetBatch($batchNo);

        Cache::put($this->totalKey($batchNo), 3, now()->addMinute());
        Cache::put($this->doneKey($batchNo), 1, now()->addMinute());
        Cache::put($this->failedKey($batchNo), 1, now()->addMinute());
        Cache::put($this->metaKey($batchNo), [
            'batch_no' => $batchNo,
            'status' => 'running',
        ], now()->addMinute());

        $this->artisan('report-status', ['batch_no' => $batchNo])
            ->expectsOutput('报表批次：' . $batchNo)
            ->expectsOutput('当前状态：运行中')
            ->expectsOutput('完成时间：-')
            ->expectsOutput('总任务数：3')
            ->expectsOutput('已完成：1')
            ->expectsOutput('失败数：1')
            ->expectsOutput('剩余数：1')
            ->expectsOutput('完成进度：66.67%')
            ->assertExitCode(0);

        $this->forgetBatch($batchNo);
    }

    private function forgetBatch(string $batchNo): void
    {
        Cache::forget($this->totalKey($batchNo));
        Cache::forget($this->doneKey($batchNo));
        Cache::forget($this->failedKey($batchNo));
        Cache::forget($this->metaKey($batchNo));
    }

    private function totalKey(string $batchNo): string
    {
        return "report:batch:{$batchNo}:total";
    }

    private function doneKey(string $batchNo): string
    {
        return "report:batch:{$batchNo}:done";
    }

    private function failedKey(string $batchNo): string
    {
        return "report:batch:{$batchNo}:failed";
    }

    private function metaKey(string $batchNo): string
    {
        return "report:batch:{$batchNo}:meta";
    }
}
