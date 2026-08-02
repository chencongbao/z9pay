<?php

namespace App\Services\User;

use App\Traits\ServiceTraits;

class TodayDepositOrderTotalAmountService
{
    use ServiceTraits;

    public function excute($user_id = 0, $amount = 0, $is_agent = 0)
    {
        $userId = intval($user_id);
        if ($userId <= 0) {
            return bob_amount_format(0);
        }

        $statsService = app(UserTodayDepositStatsService::class);
        if ($amount > 0) {
            $statsService->increase($userId, (float) $amount, 0, 0);
        }

        return bob_amount_format($statsService->amountFor($userId, (int) $is_agent));
    }

    public function update($user_id, $key, $is_agent)
    {
        return app(UserTodayDepositStatsService::class)->amountFor((int) $user_id, (int) $is_agent);
    }
}
