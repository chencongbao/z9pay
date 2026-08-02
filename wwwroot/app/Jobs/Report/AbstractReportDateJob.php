<?php

namespace App\Jobs\Report;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\App;
use Illuminate\Queue\SerializesModels;
use App\Jobs\Middleware\RateJobLimited;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\Report\ReportPendingDateService;
use App\Jobs\Concerns\InteractsWithReportRunState;

abstract class AbstractReportDateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, InteractsWithReportRunState;

    public $tries = 1;

    public $timeout = 3600;

    public $date_add;

    public function __construct(string $date_add, string $reportBatchNo = '')
    {
        $this->date_add = $date_add;
        $this->reportBatchNo = $reportBatchNo;
    }

    public function handle(): void
    {
        App::make(ReportPendingDateService::class)->addDates([$this->date_add]);
        $this->markReportJobDone();
    }

    public function middleware(): array
    {
        return [new RateJobLimited(class_basename(static::class) . "_{$this->date_add}", 1)];
    }

    abstract protected function serviceMethod(): string;
}
