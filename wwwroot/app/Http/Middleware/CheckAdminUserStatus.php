<?php

namespace App\Http\Middleware;

use Closure;
use Dcat\Admin\Admin;
use App\Models\AdminUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Dcat\Admin\Http\Auth\Permission;
use App\Services\IpWhite\CheckIpService;

class CheckAdminUserStatus
{
    public function handle(Request $request, Closure $next)
    {
        if(Admin::user()){
            $user = AdminUser::where('status',1)->where('id',Admin::user()->id)->first(['id','session_id','login_white_ip']);
            if($user){
                if ($this->isProtectedDeveloperToolRequest($request)) {
                    Permission::error();
                }

                if ($this->shouldCheckLoginWhiteIp() && !empty($user->login_white_ip)) {
                    $allowIps = bob_format_muti_data_to_array($user->login_white_ip);
                    if (!App::make(CheckIpService::class)->excute($allowIps)) {
                        return $this->logout($request);
                    }
                }

                if ($user->session_id !== session()->getId()) {
                    if (!$this->renewSessionIdByRememberLogin($user)) {
                        return $this->logout($request);
                    }
                }

                return $next($request);
            }
            return $this->logout($request);
        }
        return $next($request);
    }

    private function isProtectedDeveloperToolRequest(Request $request): bool
    {
        if (Admin::user()->isAdministrator()) {
            return false;
        }

        foreach ($this->protectedDeveloperToolPaths() as $path) {
            $fullPath = $this->adminPath($path);
            if ($request->is($fullPath) || $request->is($fullPath . '/*')) {
                return true;
            }
        }

        return false;
    }

    private function protectedDeveloperToolPaths(): array
    {
        // 开发工具可生成/查看系统结构，请求层只允许超级管理员访问；后台管理页面统一交给 Dcat 角色权限控制。
        return [
            'auth/extensions',
            'helpers/scaffold',
            'helpers/icons',
            'auth/menu',
            'auth/permissions',
            'merchant/auth/menu',
            'merchant/auth/roles',
            'merchant/auth/permissions',
            'agent/auth/menu',
            'agent/auth/roles',
            'agent/auth/permissions',
        ];
    }

    private function adminPath(string $path): string
    {
        $prefix = trim((string) config('admin.route.prefix'), '/');
        $path = trim($path, '/');

        return $prefix === '' ? $path : $prefix . '/' . $path;
    }

    private function shouldCheckLoginWhiteIp(): bool
    {
        return !App::environment(['local']) && !config('app.debug');
    }

    private function renewSessionIdByRememberLogin(AdminUser $user): bool
    {
        $guard = Admin::guard();
        if (!method_exists($guard, 'viaRemember') || !$guard->viaRemember()) {
            return false;
        }

        AdminUser::where('id', $user->id)->update([
            'session_id' => session()->getId(),
            'last_login_ip' => bob_ip(),
            'last_login_time' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    protected function logout(Request $request)
    {
        Admin::guard()->logout();

        if (config('iframe_tab.enable')) {
            $loginUrl = admin_url('auth/login');
            return response("<script>window.top.location.href = " . json_encode($loginUrl) . ";</script>", 401);
        }

        return admin_redirect('auth/login', 401);
    }
}
