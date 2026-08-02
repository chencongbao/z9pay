<?php

namespace App\Services\BlackContent;

use App\Traits\ServiceTraits;
use Illuminate\Support\Facades\App;
use App\Services\Cache\BlackContent\CacheIpService;

class CheckIpRuleService
{
    use ServiceTraits;

    public function excute($ip = null, $mid = 0)
    {
        $ip = trim((string) $ip);
        if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        $result = App::make(CacheIpService::class)->excute(false);
        if (empty($result) || !is_array($result)) {
            return false;
        }

        return $this->hasIp($result[0] ?? [], $ip) || $this->hasIp($result[intval($mid)] ?? [], $ip);
    }

    private function hasIp($data, string $ip): bool
    {
        if (!is_array($data) || empty($data)) {
            return false;
        }

        return isset($data[$ip]) || in_array($ip, $data, true);
    }
}
