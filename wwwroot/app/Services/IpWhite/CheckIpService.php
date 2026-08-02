<?php

namespace App\Services\IpWhite;

use App\Traits\ServiceTraits;

class CheckIpService
{
    use ServiceTraits;

    public function excute($verify_ip = [], $ip = ''): bool
    {
        $verifyIps = $this->formatVerifyIps($verify_ip);
        if (empty($verifyIps)) {
            return true;
        }

        $userIp = trim((string) ($ip ?: bob_ip()));
        if (!filter_var($userIp, FILTER_VALIDATE_IP)) {
            return false;
        }

        foreach ($verifyIps as $verifyIp) {
            if ($this->matchIp($userIp, $verifyIp)) {
                return true;
            }
        }

        return false;
    }

    private function formatVerifyIps($verifyIp): array
    {
        if (!is_array($verifyIp)) {
            $verifyIp = bob_format_muti_data_to_array($verifyIp);
        }

        return array_values(array_filter(array_map('trim', $verifyIp)));
    }

    private function matchIp(string $userIp, string $verifyIp): bool
    {
        if ($verifyIp === '') {
            return false;
        }

        if (strpos($verifyIp, '/') !== false) {
            return bob_ip_in_cidr($userIp, $verifyIp);
        }

        return filter_var($verifyIp, FILTER_VALIDATE_IP) && $userIp === $verifyIp;
    }
}
