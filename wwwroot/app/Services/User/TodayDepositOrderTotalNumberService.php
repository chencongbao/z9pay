<?php

namespace App\Services\User;

use App\Traits\ServiceTraits;

class TodayDepositOrderTotalNumberService
{
    use ServiceTraits;

    public function excute($user_id = 0, $number = 0, $is_agent = 0)
    {
        $userId = intval($user_id);
        if ($userId <= 0) {
            return bob_amount_format(0);
        }

        $statsService = app(UserTodayDepositStatsService::class);
        if ($number > 0) {
            $statsService->increase($userId, 0, (int) $number, 0);
        }

        return bob_amount_format($statsService->numberFor($userId, (int) $is_agent));
    }

    public function update($user_id, $key, $is_agent)
    {
        return app(UserTodayDepositStatsService::class)->numberFor((int) $user_id, (int) $is_agent);
    }
}
