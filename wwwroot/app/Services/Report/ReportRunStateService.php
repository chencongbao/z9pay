<?php

namespace App\Services\Report;

use Throwable;
use Illuminate\Support\Facades\Cache;

class ReportRunStateService
{
    private const RUNNING_KEY = 'report:command:running';
    private const LATEST_KEY = 'report:batch:latest';

    public function start(string $batchNo, int $total, array $dates = [], int $merchantCount = 0): bool
    {
        if (!Cache::add(self::RUNNING_KEY, $batchNo, now()->addHours(12))) {
            return false;
        }

        Cache::put(self::LATEST_KEY, $batchNo, now()->addDays(3));
        Cache::put($this->totalKey($batchNo), $total, now()->addDays(3));
        Cache::put($this->doneKey($batchNo), 0, now()->addDays(3));
        Cache::put($this->failedKey($batchNo), 0, now()->addDays(3));
        Cache::put($this->metaKey($batchNo), [
            'batch_no' => $batchNo,
            'status' => 'running',
            'dates' => $dates,
            'merchant_count' => $merchantCount,
            'started_at' => now()->format('Y-m-d H:i:s'),
            'finished_at' => null,
            'last_done_at' => null,
            'last_failed_at' => null,
            'last_error' => null,
        ], now()->addDays(3));

        return true;
    }

    public function addTotal(?string $batchNo, int $count = 1): void
    {
        if (empty($batchNo) || $count <= 0) {
            return;
        }

        Cache::increment($this->totalKey($batchNo), $count);
    }

    public function markDone(?string $batchNo): void
    {
        if (empty($batchNo)) {
            return;
        }

        Cache::increment($this->doneKey($batchNo));
        $this->updateMeta($batchNo, ['last_done_at' => now()->format('Y-m-d H:i:s')]);
        $this->finishIfComplete($batchNo);
    }

    public function markFailed(?string $batchNo, Throwable $exception): void
    {
        if (empty($batchNo)) {
            return;
        }

        Cache::increment($this->failedKey($batchNo));
        $this->updateMeta($batchNo, [
            'last_failed_at' => now()->format('Y-m-d H:i:s'),
            'last_error' => $exception->getMessage(),
        ]);
        $this->finishIfComplete($batchNo);
    }

    public function releaseRunning(?string $batchNo = null, string $status = 'stopped'): void
    {
        $runningBatchNo = Cache::get(self::RUNNING_KEY);
        if ($batchNo && $runningBatchNo && $runningBatchNo !== $batchNo) {
            return;
        }

        if ($batchNo) {
            $this->updateMeta($batchNo, [
                'status' => $status,
                'finished_at' => now()->format('Y-m-d H:i:s'),
            ]);
        }

        Cache::forget(self::RUNNING_KEY);
    }

    public function resetRunning(): void
    {
        $runningBatchNo = Cache::get(self::RUNNING_KEY);
        if ($runningBatchNo) {
            $this->releaseRunning($runningBatchNo, 'reset');
            return;
        }

        Cache::forget(self::RUNNING_KEY);
    }

    public function status(?string $batchNo = null): array
    {
        $isSpecifiedBatch = !empty($batchNo);
        $batchNo = $batchNo ?: Cache::get(self::RUNNING_KEY) ?: Cache::get(self::LATEST_KEY);
        if (empty($batchNo)) {
            return [];
        }

        $total = (int) Cache::get($this->totalKey($batchNo), 0);
        $done = (int) Cache::get($this->doneKey($batchNo), 0);
        $failed = (int) Cache::get($this->failedKey($batchNo), 0);
        $meta = Cache::get($this->metaKey($batchNo), []);
        if (!is_array($meta)) {
            $meta = [];
        }
        if ($isSpecifiedBatch && empty($meta) && !Cache::has($this->totalKey($batchNo)) && !Cache::has($this->doneKey($batchNo)) && !Cache::has($this->failedKey($batchNo))) {
            return [];
        }
        $completed = $done + $failed;

        return array_merge([
            'batch_no' => $batchNo,
            'status' => 'unknown',
            'dates' => [],
            'merchant_count' => 0,
            'started_at' => null,
            'finished_at' => null,
            'last_done_at' => null,
            'last_failed_at' => null,
            'last_error' => null,
        ], $meta, [
            'batch_no' => $batchNo,
            'total' => $total,
            'done' => $done,
            'failed' => $failed,
            'remaining' => max($total - $completed, 0),
            'progress' => $total > 0 ? round($completed / $total * 100, 2) : 0,
            'is_running' => Cache::get(self::RUNNING_KEY) === $batchNo,
        ]);
    }

    public function runningCacheKey(): string
    {
        return self::RUNNING_KEY;
    }

    private function finishIfComplete(string $batchNo): void
    {
        $total = (int) Cache::get($this->totalKey($batchNo), 0);
        $done = (int) Cache::get($this->doneKey($batchNo), 0);
        $failed = (int) Cache::get($this->failedKey($batchNo), 0);

        if ($total <= 0 || ($done + $failed) < $total) {
            return;
        }

        $this->releaseRunning($batchNo, $failed > 0 ? 'finished_with_failed' : 'finished');
    }

    private function updateMeta(string $batchNo, array $data): void
    {
        $meta = Cache::get($this->metaKey($batchNo), []);
        Cache::put($this->metaKey($batchNo), array_merge($meta, $data), now()->addDays(3));
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
