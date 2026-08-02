<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use \App\Traits\ResponseTraits;

class ForceChangePassword
{

    use ResponseTraits;

    public function handle(Request $request, Closure $next)
    {

        if (!Auth::guard("sanctum")->check()) {
            return $next($request);
        }

        $user = Auth::guard("sanctum")->user();

        if (!empty($user->password_changed_at)) {
            return $next($request);
        }

        if ($request->is('api/v2/users/updatePassword')) {
            return $next($request);
        }

        return $this->result(-3, '请修改密码');
    }
}
