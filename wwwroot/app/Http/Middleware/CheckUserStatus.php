<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Traits\ResponseTraits;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckUserStatus
{
    use ResponseTraits;

    public function handle(Request $request, Closure $next)
    {
        if (Auth::guard("sanctum")->check()) {
            $user = Auth::guard("sanctum")->user();
            $user = User::where('status', 1)->where('id', $user->id)->first(['id']);
            if (!$user) {
                return $this->result(-1, '请重新登录');
            }
        }
        return $next($request);
    }
}
