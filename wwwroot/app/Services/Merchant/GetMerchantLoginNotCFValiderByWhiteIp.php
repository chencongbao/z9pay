<?php

namespace App\Services\Merchant;

use App\Traits\ServiceTraits;
use Illuminate\Support\Facades\App;
use App\Services\IpWhite\CheckIpService;
use App\Services\Cache\Merchant\CacheMerchantWhiteIpByUsernameService;

class GetMerchantLoginNotCFValiderByWhiteIp
{
    use ServiceTraits;

    public function excute($username = '', $coder = ''): bool
    {
        if (empty($username) || empty($coder)) {
            return true;
        }

        $result = App::make(CacheMerchantWhiteIpByUsernameService::class)->excute($username);
        if (empty($result) || strtolower($result['coder'] ?? '') !== strtolower($coder)) {
            return true;
        }

        $ips = $result['login_white_ip'] ?? '';
        if (empty($ips)) {
            return true;
        }

        return !App::make(CheckIpService::class)->excute($ips);
    }
}
