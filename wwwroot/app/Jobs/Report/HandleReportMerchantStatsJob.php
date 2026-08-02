<?php

namespace App\Jobs\Report;

use Throwable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use App\Jobs\Middleware\RateJobLimited;
use App\Services\Report\ReportDateBuildService;

class HandleReportMerchantStatsJob extends AbstractReportDateJob
{
    public int $mid;

    public function __construct(string $date_add, string $reportBatchNo, int $mid)
    {
        parent::__construct($date_add, $reportBatchNo);
        $this->mid = $mid;
    }

    public function handle(): void
    {
        $startedAt = microtime(true);
        $this->markMerchantStatus('running');
        $stats = App::make(ReportDateBuildService::class)->buildMerchantOrderStats($this->date_add, $this->mid, function () {
            $this->markMerchantStatus('running');
        });
        Cache::put($this->statsKey(), $stats, now()->addDays(3));
        Cache::increment($this->merchantDoneKey());
        $this->markMerchantStatus('done', null, $startedAt);
        $this->markReportJobDone();
    }

    public function failed(Throwable $exception): void
    {
        Cache::increment($this->merchantFailedKey());
        $this->markMerchantStatus('failed', $exception->getMessage());
        parent::failed($exception);
    }

    public function middleware(): array
    {
        return [new RateJobLimited(class_basename(static::class) . "_{$this->date_add}_{$this->mid}", 1)];
    }

    protected function serviceMethod(): string
    {
        return 'buildMerchantOrderStats';
    }

    private function statsKey(): string
    {
        return "report:batch:{$this->reportBatchNo}:{$this->date_add}:merchant:{$this->mid}:stats";
    }

    private function merchantDoneKey(): string
    {
        return "report:batch:{$this->reportBatchNo}:{$this->date_add}:merchant_done";
    }

    private function merchantFailedKey(): string
    {
        return "report:batch:{$this->reportBatchNo}:{$this->date_add}:merchant_failed";
    }

    private function merchantStatusKey(): string
    {
        return "report:batch:{$this->reportBatchNo}:{$this->date_add}:merchant:{$this->mid}:status";
    }

    private function markMerchantStatus(string $status, ?string $error = null, ?float $startedAt = null): void
    {
        $old = Cache::get($this->merchantStatusKey(), []);
        $data = [
            'mid' => $this->mid,
            'status' => $status,
            'updated_at' => now()->format('Y-m-d H:i:s'),
        ];
        if ($status === 'running') {
            $data['started_at'] = $old['started_at'] ?? now()->format('Y-m-d H:i:s');
        }
        if ($startedAt !== null) {
            $data['duration_seconds'] = round(microtime(true) - $startedAt, 3);
        }
        if ($error !== null) {
            $data['error'] = $error;
        }

        Cache::put($this->merchantStatusKey(), $data, now()->addDays(3));
    }
}
