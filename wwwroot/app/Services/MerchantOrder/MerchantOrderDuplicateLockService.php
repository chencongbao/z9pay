<?php

namespace App\Services\MerchantOrder;

use App\Services\Cache\CacheConstPrefixService;
use Illuminate\Support\Facades\Cache;

class MerchantOrderDuplicateLockService
{
    public function lockDeposit($mid, $orderNo): bool
    {
        return $this->lock($this->depositKey($mid, $orderNo));
    }

    public function lockTransfer($mid, $orderNo): bool
    {
        return $this->lock($this->transferKey($mid, $orderNo));
    }

    public function releaseDeposit($mid, $orderNo): void
    {
        Cache::forget($this->depositKey($mid, $orderNo));
    }

    public function keepDeposit($mid, $orderNo, $ordernumber = null): void
    {
        Cache::put($this->depositKey($mid, $orderNo), $ordernumber ?: 1, now()->addDays(30));
    }

    public function keepTransfer($mid, $orderNo, $ordernumber = null): void
    {
        Cache::put($this->transferKey($mid, $orderNo), $ordernumber ?: 1, now()->addDays(30));
    }

    public function releaseTransfer($mid, $orderNo): void
    {
        Cache::forget($this->transferKey($mid, $orderNo));
    }

    private function lock(string $key): bool
    {
        return Cache::add($key, 1, now()->addHour());
    }

    private function depositKey($mid, $orderNo): string
    {
        return CacheConstPrefixService::MERCHANT_DEPOSIT_ORDERNUMBER_INFO . $mid . ':' . $orderNo;
    }

    private function transferKey($mid, $orderNo): string
    {
        return CacheConstPrefixService::MERCHANT_TRANSFER_ORDERNUMBER_INFO . $mid . ':' . $orderNo;
    }
}
