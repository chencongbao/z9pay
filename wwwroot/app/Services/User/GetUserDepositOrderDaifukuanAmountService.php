<?php

namespace App\Services\User;

use App\Traits\ServiceTraits;
use Illuminate\Support\Facades\App;
use App\Services\User\UserPendingDepositOrderStatsService;
use App\Services\SelfNewPayment\GetUserDaifukuanDepositOrderListService;

class GetUserDepositOrderDaifukuanAmountService
{
    use ServiceTraits;

    private array $items = [];

    public function excute($user_id = 0, $force = false): float
    {
        $userId = (int)$user_id;
        if ($userId <= 0) {
            return 0;
        }

        if (!$force && array_key_exists($userId, $this->items)) {
            return $this->items[$userId];
        }

        if ($force) {
            App::make(GetUserDaifukuanDepositOrderListService::class)->rebuild($userId);
        }

        $this->items[$userId] = App::make(UserPendingDepositOrderStatsService::class)->amount($userId);

        return $this->items[$userId];
    }
}
