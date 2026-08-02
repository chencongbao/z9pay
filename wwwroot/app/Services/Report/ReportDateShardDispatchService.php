<?php

namespace App\Services\Report;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use App\Jobs\Report\HandleReportFinalizeJob;
use App\Jobs\Report\HandleReportMerchantStatsJob;
use App\Services\Cache\Merchant\GetMerchantListInfoService;

class ReportDateShardDispatchService
{
    public function activeMerchantIds(): array
    {
        $merchantLists = App::make(GetMerchantListInfoService::class)->excute();
        if (empty($merchantLists)) {
            return [];
        }

        return collect($merchantLists)
            ->filter(fn ($item) => (int)($item['status'] ?? 0) === 1)
            ->pluck('id')
            ->map(fn ($id) => (int)$id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    public function startAndDispatch(string $date, int $delaySeconds = 0): ?string
    {
        $merchantIds = $this->activeMerchantIds();
        if (empty($merchantIds)) {
            return null;
        }

        $reportRunStateService = App::make(ReportRunStateService::class);
        if (Cache::has($reportRunStateService->runningCacheKey())) {
            return null;
        }

        $batchNo = $this->makeBatchNo();
        if (!$reportRunStateService->start($batchNo, count($merchantIds) + 1, [$date], count($merchantIds))) {
            return null;
        }

        $this->dispatchDate($batchNo, $date, $merchantIds, $delaySeconds);

        return $batchNo;
    }

    public function dispatchDate(string $batchNo, string $date, array $merchantIds, int $delaySeconds = 0): void
    {
        $merchantIds = array_values(array_unique(array_filter(array_map('intval', $merchantIds), fn (int $id) => $id > 0)));
        Cache::put($this->merchantIdsKey($batchNo, $date), $merchantIds, now()->addDays(3));

        foreach ($merchantIds as $mid) {
            $job = (new HandleReportMerchantStatsJob($date, $batchNo, $mid))->onQueue('count');
            if ($delaySeconds > 0) {
                $job->delay(now()->addSeconds($delaySeconds));
            }
            dispatch($job);
        }

        $finalizeJob = (new HandleReportFinalizeJob($date, $batchNo, $merchantIds))->onQueue('count')->delay(now()->addSeconds($delaySeconds + 10));
        dispatch($finalizeJob);
    }

    private function makeBatchNo(): string
    {
        return date('YmdHis') . '_' . substr(md5(uniqid('', true)), 0, 8);
    }

    private function merchantIdsKey(string $batchNo, string $date): string
    {
        return "report:batch:{$batchNo}:{$date}:merchant_ids";
    }
}
