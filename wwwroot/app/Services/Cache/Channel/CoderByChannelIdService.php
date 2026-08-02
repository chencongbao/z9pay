<?php

namespace App\Services\Cache\Channel;

use App\Models\Channel;
use App\Traits\ServiceTraits;
use Illuminate\Support\Facades\Cache;
use App\Services\Cache\CacheConstPrefixService;
use Illuminate\Contracts\Cache\LockTimeoutException;

class CoderByChannelIdService
{
    use ServiceTraits;

    public function excute(int $channelId = 0, bool $force = false): array
    {
        if ($channelId <= 0) {
            return [];
        }

        $key = CacheConstPrefixService::CHANNEL_PAYMENT_CODE_MAP . $channelId;
        if ($force) {
            return $this->refresh($channelId, $key);
        }

        $cache = Cache::get($key);
        if ($this->isValidCache($cache)) {
            return $cache;
        }

        return $this->refreshWithLock($channelId, $key);
    }

    private function refreshWithLock(int $channelId, string $key): array
    {
        try {
            return Cache::lock($key . ':lock', 10)->block(3, function () use ($channelId, $key) {
                $cache = Cache::get($key);
                if ($this->isValidCache($cache)) {
                    return $cache;
                }

                return $this->refresh($channelId, $key);
            });
        } catch (LockTimeoutException) {
            return $this->refresh($channelId, $key);
        }
    }

    private function refresh(int $channelId, string $key): array
    {
        $coders = $this->parseCoder(Channel::query()->whereKey($channelId)->value('coder'));
        Cache::forever($key, $coders);

        return $coders;
    }

    private function parseCoder(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        $paymentCodeMap = [];
        foreach (config('payment', []) as $payment) {
            $paymentId = (int) ($payment['id'] ?? 0);
            $paymentCode = trim((string) ($payment['code'] ?? ''));
            if ($paymentId > 0 && $paymentCode !== '') {
                $paymentCodeMap[$paymentCode] = $paymentId;
            }
        }

        $coders = [];

        foreach (preg_split('/\r\n|\r|\n/', $value) as $line) {
            $line = bob_replacement_empty($line);
            if ($line === '' || strpos($line, '=') === false) {
                continue;
            }

            [$paymentCode, $channelCode] = array_map('trim', explode('=', $line, 2));
            if ($paymentCode === '' || $channelCode === '' || !isset($paymentCodeMap[$paymentCode])) {
                continue;
            }

            $coders[$paymentCodeMap[$paymentCode]] = $channelCode;
        }

        return $coders;
    }

    private function isValidCache($cache): bool
    {
        if (!is_array($cache)) {
            return false;
        }

        foreach ($cache as $paymentId => $channelCode) {
            if ((int) $paymentId <= 0 || !is_string($channelCode) || $channelCode === '') {
                return false;
            }
        }

        return true;
    }
}
