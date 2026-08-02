<?php

namespace App\Services\User;

use App\Models\User;
use App\Traits\ServiceTraits;

class GetUserCommisonRateService
{
    use ServiceTraits;

    public function excute($userId = 0, $paymentId = 0): float
    {
        $user = User::query()->whereKey(intval($userId))->first([
            'id',
            'user_rate',
            'deposit_user_rate',
            'user_deposit_payment_rate',
        ]);
        if (!$user) {
            return 0;
        }

        return $this->resolve($user, $paymentId);
    }

    public function resolve(User $user, $paymentId = 0): float
    {
        $rate = floatval($user->user_rate);
        if (floatval($user->deposit_user_rate) > 0) {
            $rate = floatval($user->deposit_user_rate);
        }

        foreach ((array) $user->user_deposit_payment_rate as $item) {
            if (
                intval($item['payment_id'] ?? 0) === intval($paymentId)
                && floatval($item['deposit_user_rate'] ?? 0) > 0
            ) {
                $rate = floatval($item['deposit_user_rate']);
            }
        }

        return $rate / 100;
    }
}
