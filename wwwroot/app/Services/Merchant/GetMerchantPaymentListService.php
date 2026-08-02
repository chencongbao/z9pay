<?php

namespace App\Services\Merchant;

use App\Traits\ServiceTraits;
use App\Models\MerchantPayment;

class GetMerchantPaymentListService
{
    use ServiceTraits;

    private const TRANSFER_PAYMENT_ID = 7;

    public function excute($mid = 0, bool $depositOnly = false): array
    {
        $mid = (int) $mid;
        if ($mid <= 0) {
            return [];
        }

        $paymentIds = MerchantPayment::query()->where('merchant_user_id', $mid)->distinct()->pluck('payment_id');

        return collect(config('payment', []))
            ->filter(fn ($item) => is_array($item) && (int) ($item['id'] ?? 0) > 0 && isset($item['name']))
            ->whereIn('id', $paymentIds)
            ->when($depositOnly, fn ($items) => $items->reject(fn ($item) => (int) $item['id'] === self::TRANSFER_PAYMENT_ID))
            ->map(function ($item) {
                $item['bname'] = '【#' . $item['id'] . '】' . $item['name'];

                return $item;
            })
            ->values()
            ->toArray();
    }
}
