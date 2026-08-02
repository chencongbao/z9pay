<?php

namespace App\Services\Cache\MerchantChannel;

use App\Traits\ServiceTraits;
use App\Models\MerchantChannel;
use Illuminate\Support\Facades\Cache;
use App\Services\Cache\CacheConstPrefixService;

class GetMerchantChannelListService
{
    use ServiceTraits;

    private const CACHE_MINUTES = 30;

    public function excute($mid = 0, $payment_id = 0, $force = false): array
    {
        $mid = intval($mid);
        $payment_id = intval($payment_id);
        if ($mid <= 0 || $payment_id <= 0) {
            return [];
        }

        if ($force) {
            return $this->update($mid, $payment_id);
        }

        return Cache::remember($this->cacheKey($mid, $payment_id), now()->addMinutes(self::CACHE_MINUTES), function () use ($mid, $payment_id) {
            return $this->buildData($mid, $payment_id);
        });
    }

    public function update($mid, $payment_id): array
    {
        $mid = intval($mid);
        $payment_id = intval($payment_id);
        if ($mid <= 0 || $payment_id <= 0) {
            return [];
        }

        $data = $this->buildData($mid, $payment_id);
        Cache::put($this->cacheKey($mid, $payment_id), $data, now()->addMinutes(self::CACHE_MINUTES));

        return $data;
    }

    private function buildData(int $mid, int $payment_id): array
    {
        $data = [];
        $result = MerchantChannel::query()
            ->where('merchant_user_id', $mid)
            ->where('payment_id', $payment_id)
            ->where('status', 1)
            ->select([
                'id',
                'merchant_user_id',
                'payment_id',
                'channel_id',
                'deposit_fee',
                'fee',
                'float_status',
                'settlement_mode',
                'settlement_time',
                'priority',
                'weight',
                'pay_min_amount',
                'pay_max_amount',
                'collection_min_amount',
                'collection_max_amount',
            ])
            ->with(['channel' => function ($query) {
                $query->select([
                    'id',
                    'name',
                    'status',
                    'classname',
                    'currency',
                    'is_real_name',
                    'transfer_payment',
                ]);
            }])
            ->orderBy('priority', 'asc')
            ->orderBy('id', 'desc')
            ->get();

        return $result->map(function ($item) {
            $data = $item->toArray();
            $channel = $data['channel'] ?? [];
            unset($data['channel']);

            $data['channel_name'] = $channel['name'] ?? '';
            $data['channel_status'] = $channel['status'] ?? 0;
            $data['channel_classname'] = $channel['classname'] ?? '';
            $data['channel_currency'] = $channel['currency'] ?? '';
            $data['channel_is_real_name'] = $channel['is_real_name'] ?? 0;
            $data['currency'] = $channel['currency'] ?? '';
            $data['transfer_payment'] = $channel['transfer_payment'] ?? '';

            return $data;
        })->values()->all();
    }

    private function cacheKey(int $mid, int $paymentId): string
    {
        return CacheConstPrefixService::MERCHANT_CHANNEL_DETAIL_LIST . $mid . '_' . $paymentId;
    }
}
