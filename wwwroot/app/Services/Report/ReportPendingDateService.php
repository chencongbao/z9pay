<?php

namespace App\Services\Report;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;

class ReportPendingDateService
{
    private const PENDING_DATES_KEY = 'report:pending_dates';

    public function addDates(iterable $dates): int
    {
        $validDates = [];
        foreach ($dates as $date) {
            $date = $this->normalizeDate((string)$date);
            if ($date !== null) {
                $validDates[] = $date;
            }
        }

        $validDates = array_values(array_unique($validDates));
        if (empty($validDates)) {
            return 0;
        }

        $added = (int)Redis::sadd(self::PENDING_DATES_KEY, ...$validDates);
        Redis::expire(self::PENDING_DATES_KEY, 86400 * 7);

        return $added;
    }

    public function nextDate(): ?string
    {
        $dates = $this->dates();
        if (empty($dates)) {
            return null;
        }

        sort($dates);

        return $dates[0];
    }

    public function dates(): array
    {
        return array_values(array_filter(array_map(function ($date) {
            return $this->normalizeDate((string)$date);
        }, Redis::smembers(self::PENDING_DATES_KEY) ?: [])));
    }

    public function removeDate(string $date): void
    {
        $date = $this->normalizeDate($date);
        if ($date === null) {
            return;
        }

        Redis::srem(self::PENDING_DATES_KEY, $date);
    }

    private function normalizeDate(string $date): ?string
    {
        $date = trim($date);
        if ($date === '') {
            return null;
        }

        try {
            return Carbon::parse($date)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
