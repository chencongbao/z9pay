<?php

namespace App\Services\IpWhite;

use App\Models\IpCountryRange;
use App\Traits\ServiceTraits;

class RandomIpFromDbService
{
    use ServiceTraits;

    public function excute($currency_id = 0)
    {
        $maxCdf = IpCountryRange::query()->where('currency_id', intval($currency_id))->max('cdf_end');
        if (!$maxCdf || $maxCdf <= 0) {
            return bob_ip();
        }

        $pick = random_int(1, (int) $maxCdf);

        $row = IpCountryRange::query()
            ->where('currency_id', intval($currency_id))
            ->where('cdf_end', '>=', $pick)
            ->orderBy('cdf_end', 'asc')
            ->first();

        if (!$row) {
            throw new \Exception('Failed to pick a range row.');
        }

        $from = (int) $row->begin_long;
        $to = (int) $row->end_long;
        $ipLong = $from + random_int(0, $to - $from);

        return $this->longToIpUnsigned($ipLong);
    }

    protected function longToIpUnsigned(int $long): string
    {
        $a = ($long >> 24) & 0xFF;
        $b = ($long >> 16) & 0xFF;
        $c = ($long >> 8) & 0xFF;
        $d = $long & 0xFF;

        return "{$a}.{$b}.{$c}.{$d}";
    }
}
