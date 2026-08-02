<?php

namespace App\Services\SelfNewPayment;

use App\Traits\ServiceTraits;
use App\Services\UserBank\UserBankTodayStatsService;

class GetUserBankTodayDepositTotalAmountService
{
    use ServiceTraits;

    public function excute($user_bank_id = 0, $amount = 0)
    {
        $userBankId = (int) $user_bank_id;
        if ($userBankId <= 0) {
            return bob_amount_format(0);
        }

        return bob_amount_format(app(UserBankTodayStatsService::class)->amountFor($userBankId));
    }

    public function update($user_bank_id, $key)
    {
        return app(UserBankTodayStatsService::class)->amountFor((int) $user_bank_id);
    }
}
