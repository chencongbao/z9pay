<?php

namespace App\Http\Middleware;

use App\Traits\ResponseTraits;
use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    use ResponseTraits;
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        if (! $request->expectsJson()) {
            if (request()->is('api/*')) {
                return $this->result(-1, '未登录');
            }
            return route('login');
        }
    }
}
