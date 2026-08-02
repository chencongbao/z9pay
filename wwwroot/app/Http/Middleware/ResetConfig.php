<?php

namespace App\Http\Middleware;

use Closure;
use Dcat\Admin\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;

class ResetConfig
{
    private static ?array $baseAdminConfig = null;

    public function handle(Request $request, Closure $next)
    {
        $this->setConfig($request);
        return $next($request);
    }

    protected function setConfig($request)
    {
        $adminTitle = $this->adminTitle($request);
        $adminName = $this->adminName($request);
        $adminLogo = $this->adminLogo($request);
        $adminLogoMini = $this->adminLogoMini($request);
        $merchantTitle = $this->appAdminDisplayName('merchant-admin', 'title');
        $agentTitle = $this->appAdminDisplayName('agent-admin', 'title');

        config(['admin.logo-mini' => $adminLogoMini, 'admin.logo' => $adminLogo, 'admin.name' => $adminName]);
        config([
            'admin.title' => $adminTitle,
            'merchant-admin.title' => $merchantTitle,
            'merchant-admin.logo-mini' => mb_substr($merchantTitle, 0, 1),
            'merchant-admin.logo' => $merchantTitle,
            'merchant-admin.name' => $merchantTitle,
            'agent-admin.title' => $agentTitle,
            'agent-admin.logo-mini' => mb_substr($agentTitle, 0, 1),
            'agent-admin.logo' => $agentTitle,
            'agent-admin.name' => $agentTitle,
        ]);
        Admin::title($adminTitle);
        config(['filesystems.disks.public.url' => $request->getScheme() . '://' . $request->getHost()."/storage"]);
    }

    private function adminTitle(Request $request): string
    {
        if ($this->isMerchantAdmin()) {
            return $this->appAdminDisplayName('merchant-admin', 'title');
        }

        if ($this->isAgentAdmin()) {
            return $this->appAdminDisplayName('agent-admin', 'title');
        }

        return $this->baseAdminDisplayName('title');
    }

    private function adminName(Request $request): string
    {
        if ($this->isMerchantAdmin()) {
            return $this->appAdminDisplayName('merchant-admin', 'name');
        }

        if ($this->isAgentAdmin()) {
            return $this->appAdminDisplayName('agent-admin', 'name');
        }

        return $this->baseAdminDisplayName('name');
    }

    private function adminLogo(Request $request): string
    {
        if ($this->isMerchantAdmin()) {
            return $this->appAdminDisplayName('merchant-admin', 'logo');
        }

        if ($this->isAgentAdmin()) {
            return $this->appAdminDisplayName('agent-admin', 'logo');
        }

        return $this->baseAdminDisplayName('logo');
    }

    private function adminLogoMini(Request $request): string
    {
        if ($this->isMerchantAdmin()) {
            return mb_substr($this->appAdminDisplayName('merchant-admin', 'logo-mini'), 0, 1);
        }

        if ($this->isAgentAdmin()) {
            return mb_substr($this->appAdminDisplayName('agent-admin', 'logo-mini'), 0, 1);
        }

        return mb_substr($this->baseAdminDisplayName('logo-mini'), 0, 1);
    }

    private function isMerchantAdmin(): bool
    {
        return Admin::app()->getName() === 'merchant-admin';
    }

    private function isAgentAdmin(): bool
    {
        return Admin::app()->getName() === 'agent-admin';
    }

    private function baseAdminConfig(?string $key = null): array|string|null
    {
        if (self::$baseAdminConfig === null) {
            self::$baseAdminConfig = [
                'title' => config('admin-base.title'),
                'name' => config('admin-base.name'),
                'logo' => config('admin-base.logo'),
                'logo-mini' => config('admin-base.logo-mini'),
                'lang_key' => config('admin-base.lang_key'),
            ];
        }

        return $key === null ? self::$baseAdminConfig : (self::$baseAdminConfig[$key] ?? null);
    }

    private function baseAdminDisplayName(string $fallbackKey): string
    {
        $langKey = trim((string)$this->baseAdminConfig('lang_key'));

        if ($langKey !== '' && Lang::has('admin.' . $langKey)) {
            return __('admin.' . $langKey);
        }

        return (string)$this->baseAdminConfig($fallbackKey);
    }

    private function appAdminDisplayName(string $configName, string $fallbackKey): string
    {
        $langKey = trim((string)config($configName . '.lang_key'));

        if ($langKey !== '' && Lang::has('admin.' . $langKey)) {
            return __('admin.' . $langKey);
        }

        return (string)config($configName . '.' . $fallbackKey);
    }
}
