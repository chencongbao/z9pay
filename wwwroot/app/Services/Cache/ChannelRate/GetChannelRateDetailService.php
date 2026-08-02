<?php

namespace App\Services\Cache\ChannelRate;

use App\Models\ChannelRate;
use App\Traits\ServiceTraits;
use Illuminate\Support\Facades\Cache;
use App\Services\Cache\CacheConstPrefixService;

class GetChannelRateDetailService
{
    use ServiceTraits;

    public function excute($channel_id = 0, $payment_id = 0, $force = 0, $amount = null)
    {
        return $this->resolveRateValue($this->getConfig($channel_id, $payment_id, $force), $amount);
    }

    public function calculateCost($channel_id, $payment_id, float $amount, $force = 0): float
    {
        $config = $this->getConfig($channel_id, $payment_id, $force);
        $range = $this->matchRange($config['rate_ranges'] ?? [], $amount);

        if ((int)($config['type'] ?? 0) === 1) {
            return (float)($range['fixed_rate'] ?? $config['fixed_rate'] ?? 0);
        }

        return $amount * (float)($range['rate'] ?? $config['rate'] ?? 0) / 100;
    }

    protected function update($channel_id = 0, $payment_id = 0, $key = '')
    {
        $model = ChannelRate::query()->where('channel_id', $channel_id)->where('payment_id', $payment_id)->first(['rate', 'type', 'fixed_rate', 'rate_ranges']);
        $config = $model ? [
            'type' => (int)$model->type,
            'rate' => (float)$model->rate,
            'fixed_rate' => (float)$model->fixed_rate,
            'rate_ranges' => is_array($model->rate_ranges) ? $model->rate_ranges : [],
        ] : [
            'type' => 0,
            'rate' => 0,
            'fixed_rate' => 0,
            'rate_ranges' => [],
        ];

        Cache::forever($key, $config);

        return $config;
    }

    private function resolveRateValue(array $config, $amount = null): float
    {
        $range = $amount === null ? [] : $this->matchRange($config['rate_ranges'] ?? [], (float)$amount);
        $type = (int)($config['type'] ?? 0);
        $rate = (float)($range['rate'] ?? $config['rate'] ?? 0);
        $fixedRate = (float)($range['fixed_rate'] ?? $config['fixed_rate'] ?? 0);

        return $type === 0 ? $rate / 100 : $fixedRate;
    }

    private function getConfig($channelId, $paymentId, $force): array
    {
        $key = CacheConstPrefixService::CHANNEL_RATE_DETAIL . intval($channelId) . '_' . intval($paymentId);
        if ($force) {
            return $this->update($channelId, $paymentId, $key);
        }

        $cache = Cache::get($key);

        return is_array($cache) ? $cache : $this->update($channelId, $paymentId, $key);
    }

    private function matchRange(array $ranges, float $amount): array
    {
        foreach ($ranges as $range) {
            if (!is_array($range)) {
                continue;
            }

            $minAmount = (float)($range['min_amount'] ?? 0);
            $maxAmount = (float)($range['max_amount'] ?? 0);
            if ($amount >= $minAmount && ($maxAmount <= 0 || $amount < $maxAmount)) {
                return $range;
            }
        }

        return [];
    }
}
