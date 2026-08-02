<?php

namespace App\Services\DepositOrder;

use App\Traits\ServiceTraits;

class GetCashierUrlService
{
    use ServiceTraits;

    public function excute($merchant_info = [],$ordernumber = '')
    {
        $domain = isset($merchant_info['cashier_domain']) && !empty($merchant_info['cashier_domain']) ? $merchant_info['cashier_domain'] : config('default.cashier_domain', '');
        $domain = $this->normalizeDomain($domain);
        if ($domain === '') {
            $this->error = '收银台域名未配置';
            return '';
        }

        $path =  route('cashier', ['no' => $ordernumber],false);
        return $domain . $path;
    }


    private function normalizeDomain($url)
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $url = rtrim($url, '/');
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        return strtolower($url);
    }
}
