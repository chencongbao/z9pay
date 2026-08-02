<?php

namespace App\Http\Middleware;

use Closure;
use Dcat\Admin\Admin;
use App\Models\AgentUser;
use Illuminate\Http\Request;

class CheckAgentUserStatus
{
    public function handle(Request $request, Closure $next)
    {
        $adminUser = Admin::user();
        if (!$adminUser) {
            return $next($request);
        }

        $user = AgentUser::where('status', 1)->whereKey($adminUser->id)->first(['id', 'session_id']);
        if (!$user) {
            return $this->logout();
        }

        $sessionId = $request->session()->getId();
        if ($user->session_id !== $sessionId && !$this->renewSessionIdByRememberLogin($user, $sessionId)) {
            return $this->logout();
        }

        return $next($request);
    }

    private function renewSessionIdByRememberLogin(AgentUser $user, string $sessionId): bool
    {
        $guard = Admin::guard();
        if (!method_exists($guard, 'viaRemember') || !$guard->viaRemember()) {
            return false;
        }

        AgentUser::whereKey($user->id)->update([
            'session_id' => $sessionId,
            'last_login_ip' => bob_ip(),
            'last_login_time' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    private function logout()
    {
        Admin::guard()->logout();

        return redirect(admin_url('auth/login'));
    }
}
