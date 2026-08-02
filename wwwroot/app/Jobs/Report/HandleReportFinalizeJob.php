<?php

namespace App\Jobs\Report;

use RuntimeException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use App\Services\Report\ReportRunStateService;
use App\Services\Report\ReportDateBuildService;

class HandleReportFinalizeJob extends AbstractReportDateJob
{
    private const MERCHANT_SHARD_STALE_SECONDS = 600;

    public $tries = 720;

    public int $merchantCount;

    public array $merchantIds;

    public function __construct(string $date_add, string $reportBatchNo, array $merchantIds)
    {
        parent::__construct($date_add, $reportBatchNo);
        $this->merchantIds = array_values(array_unique(array_map('intval', $merchantIds)));
        $this->merchantCount = count($this->merchantIds);
    }

    public function handle(): void
    {
        $done = (int) Cache::get($this->merchantDoneKey(), 0);
        $failed = (int) Cache::get($this->merchantFailedKey(), 0);
        if ($failed > 0) {
            throw new RuntimeException("报表商户分片统计存在失败，已停止汇总：{$this->date_add}");
        }

        if ($done < $this->merchantCount) {
            $staleMerchantIds = $this->staleMerchantIds();
            if (!empty($staleMerchantIds)) {
                foreach ($staleMerchantIds as $mid) {
                    $this->markMerchantFailed($mid, '商户分片心跳超时，队列任务可能已中断');
                }

                throw new RuntimeException("报表商户分片心跳超时，已停止汇总：{$this->date_add}，商户：" . implode(',', $staleMerchantIds));
            }

            Cache::put($this->finalizeStatusKey(), [
                'status' => 'waiting',
                'done' => $done,
                'failed' => $failed,
                'merchant_count' => $this->merchantCount,
                'updated_at' => now()->format('Y-m-d H:i:s'),
            ], now()->addDays(3));
            $this->release(30);
            return;
        }

        Cache::put($this->finalizeStatusKey(), [
            'status' => 'running',
            'done' => $done,
            'failed' => $failed,
            'merchant_count' => $this->merchantCount,
            'updated_at' => now()->format('Y-m-d H:i:s'),
        ], now()->addDays(3));
        $merchantStats = [];
        foreach ($this->merchantIds as $mid) {
            $stats = Cache::get($this->statsKey($mid), []);
            if (!empty($stats)) {
                $merchantStats[] = $stats;
            }
        }

        App::make(ReportDateBuildService::class)->finalizeMerchantStats($this->date_add, $merchantStats);
        $this->clearMerchantStats();
        Cache::put($this->finalizeStatusKey(), [
            'status' => 'done',
            'done' => $done,
            'failed' => $failed,
            'merchant_count' => $this->merchantCount,
            'updated_at' => now()->format('Y-m-d H:i:s'),
        ], now()->addDays(3));
        $this->markReportJobDone();
    }

    protected function serviceMethod(): string
    {
        return 'finalizeMerchantStats';
    }

    private function clearMerchantStats(): void
    {
        foreach ($this->merchantIds as $mid) {
            Cache::forget($this->statsKey($mid));
        }

        Cache::forget($this->merchantDoneKey());
        Cache::forget($this->merchantFailedKey());
    }

    private function statsKey(int $mid): string
    {
        return "report:batch:{$this->reportBatchNo}:{$this->date_add}:merchant:{$mid}:stats";
    }

    private function merchantDoneKey(): string
    {
        return "report:batch:{$this->reportBatchNo}:{$this->date_add}:merchant_done";
    }

    private function merchantFailedKey(): string
    {
        return "report:batch:{$this->reportBatchNo}:{$this->date_add}:merchant_failed";
    }

    private function merchantStatusKey(int $mid): string
    {
        return "report:batch:{$this->reportBatchNo}:{$this->date_add}:merchant:{$mid}:status";
    }

    private function finalizeStatusKey(): string
    {
        return "report:batch:{$this->reportBatchNo}:{$this->date_add}:finalize_status";
    }

    private function staleMerchantIds(): array
    {
        $staleMerchantIds = [];
        foreach ($this->merchantIds as $mid) {
            $status = Cache::get($this->merchantStatusKey((int)$mid), []);
            if (($status['status'] ?? '') !== 'running') {
                continue;
            }

            $updatedAt = strtotime((string)($status['updated_at'] ?? ''));
            if ($updatedAt > 0 && time() - $updatedAt > self::MERCHANT_SHARD_STALE_SECONDS) {
                $staleMerchantIds[] = (int)$mid;
            }
        }

        return $staleMerchantIds;
    }

    private function markMerchantFailed(int $mid, string $error): void
    {
        Cache::put($this->merchantStatusKey($mid), [
            'mid' => $mid,
            'status' => 'failed',
            'error' => $error,
            'updated_at' => now()->format('Y-m-d H:i:s'),
        ], now()->addDays(3));
        Cache::increment($this->merchantFailedKey());
        App::make(ReportRunStateService::class)->markFailed($this->reportBatchNo, new RuntimeException($error));
    }
}
