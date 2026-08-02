<?php

namespace App\Services\Throttles;

use App\Traits\ThrottlesLogins;

class MerchantUserLoginThrottleService
{
    use ThrottlesLogins;

    public function username()
    {
        return 'username';
    }

    protected function throttlePrefix(): string
    {
        return 'merchant_admin';
    }
}
