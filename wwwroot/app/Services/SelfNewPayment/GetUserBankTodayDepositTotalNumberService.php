<?php

namespace App\Services\SelfNewPayment;

use App\Traits\ServiceTraits;
use App\Services\UserBank\UserBankTodayStatsService;

class GetUserBankTodayDepositTotalNumberService
{
    use ServiceTraits;

    public function excute($user_bank_id = 0, $number = 0)
    {
        $userBankId = (int) $user_bank_id;
        if ($userBankId <= 0) {
            return bob_amount_format(0);
        }

        return bob_amount_format(app(UserBankTodayStatsService::class)->numberFor($userBankId));
    }

    public function update($user_bank_id, $key)
    {
        return app(UserBankTodayStatsService::class)->numberFor((int) $user_bank_id);
    }
}
