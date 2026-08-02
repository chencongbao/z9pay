<?php

namespace App\Services\BlackContent;

use itbdw\Ip\IpLocation;
use App\Traits\ServiceTraits;
use Illuminate\Support\Facades\App;
use App\Services\Cache\BlackContent\CacheAreaService;
use App\Services\Cache\BlackContent\CacheCashierUserIpService;

class CheckCashierAreaService
{
    use ServiceTraits;

    public function excute(): bool
    {
        $ip = bob_ip();
        $cashierUserIpCache = App::make(CacheCashierUserIpService::class);
        $ips = $cashierUserIpCache->excute();
        if ($this->hasCachedIp($ips, $ip)) {
            return false;
        }

        $areaList = App::make(CacheAreaService::class)->excute();
        if (empty($areaList) || !is_array($areaList)) {
            return true;
        }

        $location = IpLocation::getLocation($ip);
        foreach ($areaList as $areaItem) {
            if ($this->matchArea($location, $this->parseArea($areaItem))) {
                $cashierUserIpCache->add($ip);

                return false;
            }
        }

        return true;
    }

    private function hasCachedIp($ips, string $ip): bool
    {
        if (!is_array($ips) || empty($ips)) {
            return false;
        }

        return isset($ips[$ip]) || in_array($ip, $ips, true);
    }

    private function matchArea(array $location, array $area): bool
    {
        $count = count($area);
        if ($count === 3 && !empty($location['county']) && !empty($location['city']) && !empty($location['province'])) {
            return $this->locationContains($location, $area);
        }

        if ($count === 2 && !empty($location['city']) && !empty($location['province'])) {
            return $this->locationContains($location, $area);
        }

        if ($count === 1 && !empty($location['province'])) {
            return $this->locationContains($location, $area);
        }

        return false;
    }

    private function locationContains(array $location, array $area): bool
    {
        $locationArea = (string) ($location['area'] ?? '');
        foreach ($area as $item) {
            if ($item === '' || !str_contains($locationArea, $item)) {
                return false;
            }
        }

        return true;
    }

    private function parseArea($area): array
    {
        $area = str_replace([',', '，'], '=', (string) $area);

        return array_values(array_filter(array_map('trim', explode('=', $area)), fn ($item) => $item !== ''));
    }
}
