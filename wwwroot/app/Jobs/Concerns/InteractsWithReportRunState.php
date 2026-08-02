<?php

namespace App\Jobs\Concerns;

use Throwable;
use Illuminate\Support\Facades\App;
use App\Services\Report\ReportRunStateService;

trait InteractsWithReportRunState
{
    public $reportBatchNo = '';

    protected function addReportJobs(int $count = 1): void
    {
        App::make(ReportRunStateService::class)->addTotal($this->reportBatchNo, $count);
    }

    protected function markReportJobDone(): void
    {
        App::make(ReportRunStateService::class)->markDone($this->reportBatchNo);
    }

    public function failed(Throwable $exception): void
    {
        App::make(ReportRunStateService::class)->markFailed($this->reportBatchNo, $exception);
    }
}
