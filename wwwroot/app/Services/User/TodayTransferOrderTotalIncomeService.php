<?php

namespace App\Services\User;

use App\Traits\ServiceTraits;

class TodayTransferOrderTotalIncomeService
{
    use ServiceTraits;

    public function excute($user_id = 0, $amount = 0, $is_agent = 0)
    {
        $userId = intval($user_id);
        if ($userId <= 0) {
            return bob_amount_format(0);
        }

        $statsService = app(UserTodayTransferStatsService::class);
        if ($amount > 0) {
            $statsService->increase($userId, 0, 0, (float) $amount);
        }

        return bob_amount_format($statsService->incomeFor($userId, (int) $is_agent));
    }

    public function update($user_id, $key, $is_agent, $amount)
    {
        return app(UserTodayTransferStatsService::class)->incomeFor((int) $user_id, (int) $is_agent);
    }
}
