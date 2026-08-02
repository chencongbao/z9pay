<?php

namespace App\Services\Throttles;

use App\Traits\ThrottlesLogins;

class UserLoginThrottleService
{
    use ThrottlesLogins;

    public function username()
    {
        return 'username';
    }

    protected function throttlePrefix(): string
    {
        return 'user_api_v2';
    }
}
