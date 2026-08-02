<?php

namespace App\Services\Common;

class BalanceNoticeIntervalService
{
    public function minutes(): int
    {
        $setting = bob_admin_setting('telegram_balance_notice_interval');
        if ($setting === '' || is_null($setting)) {
            return 3;
        }

        return max(0, intval($setting));
    }
}
