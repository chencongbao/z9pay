<?php

namespace App\Services\Throttles;

use App\Traits\ThrottlesLogins;

class AgentUserLoginThrottleService
{
    use ThrottlesLogins;

    public function username()
    {
        return 'username';
    }

    protected function throttlePrefix(): string
    {
        return 'agent_admin';
    }
}
