<?php

namespace App\Services\Throttles;

use App\Traits\ThrottlesLogins;

class AdminUserLoginThrottleService
{
    use ThrottlesLogins;

    public function username()
    {
        return 'username';
    }

    protected function throttlePrefix(): string
    {
        return 'admin';
    }
}
