<?php

namespace App\Services\Report;

use App\Models\DepositOrder;
use App\Models\TransferOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;

class OrderStatusReportRepairService
{
    public function forDepositOrder(?DepositOrder $order = null): void
    {
        if (!$order) {
            return;
        }

        $dates = $this->historyDates($order);
        if ($dates->isEmpty()) {
            return;
        }

        App::make(ReportPendingDateService::class)->addDates($dates);
    }

    public function forTransferOrder(?TransferOrder $order = null): void
    {
        if (!$order) {
            return;
        }

        $dates = $this->historyDates($order);
        if ($dates->isEmpty()) {
            return;
        }

        App::make(ReportPendingDateService::class)->addDates($dates);
    }

    private function historyDates(object $order): Collection
    {
        $createdDate = null;
        $successDate = null;

        try {
            if (!empty($order->created_at)) {
                $createdDate = Carbon::parse($order->created_at)->toDateString();
            }
            if (!empty($order->success_time)) {
                $successDate = Carbon::createFromTimestamp((int) $order->success_time)->toDateString();
            }
        } catch (\Throwable) {
            return collect();
        }

        $today = Carbon::today()->toDateString();
        if (!$createdDate || $createdDate >= $today) {
            return collect();
        }

        return collect([$createdDate, $successDate])
            ->filter()
            ->map(fn (string $date) => Carbon::parse($date)->toDateString())
            ->filter(fn (string $date) => $date < $today)
            ->unique()
            ->values();
    }
}
