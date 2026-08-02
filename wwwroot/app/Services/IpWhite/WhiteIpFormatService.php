<?php

namespace App\Services\IpWhite;

use RuntimeException;
use App\Traits\ServiceTraits;

class WhiteIpFormatService
{
    use ServiceTraits;

    public function excute($value = null, string $fieldName = 'IP白名单'): string
    {
        return $this->normalize($value, $fieldName);
    }

    public function normalize($value, string $fieldName = 'IP白名单'): string
    {
        $ips = bob_format_muti_data_to_array($value);
        if (empty($ips)) {
            return '';
        }

        foreach ($ips as $ip) {
            if (!$this->isValidWhiteIpItem($ip)) {
                throw new RuntimeException($fieldName . '包含非法IP：' . $ip);
            }
        }

        return implode(',', array_values(array_unique($ips)));
    }

    public function isValidWhiteIpItem(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return true;
        }

        if (strpos($ip, '/') === false) {
            return false;
        }

        [$address, $bits] = explode('/', $ip, 2);
        if (!filter_var($address, FILTER_VALIDATE_IP) || !ctype_digit($bits)) {
            return false;
        }

        $maxBits = filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? 128 : 32;

        return intval($bits) >= 0 && intval($bits) <= $maxBits;
    }
}
