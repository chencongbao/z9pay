<?php

namespace App\Extendtions\Okx;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class Quotes
{

    public $data = [];


    public function queryAll()
    {
        $result = $this->fetchData(bob_admin_setting('okx_all_payment_method') ?: 'all',bob_admin_setting('okx_all_user_type') ?: 'all',bob_admin_setting('okx_all_side') ?: 'sell');
        if (!empty($result)) {
            $result = array_slice($result, 0, 10);
            foreach ($result as $k => $v) {
                $this->data[] = [
                    'name' => $v['nickName'],
                    'price' => $v['price']
                ];
            }
            return $this->data;
        }
        return;
    }

    public function queryBank()
    {
        $result = $this->fetchData(bob_admin_setting('okx_bank_payment_method') ?: 'bank',bob_admin_setting('okx_bank_user_type') ?: 'all',bob_admin_setting('okx_bank_side') ?: 'sell');
        if (!empty($result)) {
            $result = array_slice($result, 0, 10);
            foreach ($result as $k => $v) {
                $this->data[] = [
                    'name' => $v['nickName'],
                    'price' => $v['price']
                ];
            }
            return $this->data;
        }
        return;
    }

    public function queryTaobao()
    {
        $result = $this->fetchData(bob_admin_setting('okx_alipay_payment_method') ?: 'aliPay',bob_admin_setting('okx_alipay_user_type') ?: 'all',bob_admin_setting('okx_alipay_side') ?: 'sell');
        if (!empty($result)) {
            $result = array_slice($result, 0, 10);
            foreach ($result as $k => $v) {
                $this->data[] = [
                    'name' => $v['nickName'],
                    'price' => $v['price']
                ];
            }
            return $this->data;
        }
        return;
    }


    public function queryWeixin()
    {
        $result = $this->fetchData(bob_admin_setting('okx_weixin_payment_method') ?: 'wxPay',bob_admin_setting('okx_weixin_user_type') ?: 'all',bob_admin_setting('okx_weixin_side') ?: 'sell');
        if (!empty($result)) {
            $result = array_slice($result, 0, 10);
            foreach ($result as $k => $v) {
                $this->data[] = [
                    'name' => $v['nickName'],
                    'price' => $v['price']
                ];
            }
            return $this->data;
        }
        return;
    }

    //https://www.okx.com/v3/c2c/tradingOrders/books?quoteCurrency=CNY&baseCurrency=USDT&paymentMethod=bank&side=sell&userType=all&t=1769569062197
    //userType  all=大众 blockTrade=未知,vip=vip
    public function fetchData($paymentMethod = 'all', $userType = 'all', $side = 'sell')
    {
        $cacheKey = 'okx_quotes:' . md5($paymentMethod . '|' . $userType . '|' . $side);

        return Cache::remember($cacheKey, now()->addSeconds(10), function () use ($paymentMethod, $userType, $side) {
            $data = [
                'quoteCurrency' => 'CNY',
                'baseCurrency' => 'USDT',
                'side' => $side,
                'paymentMethod' => $paymentMethod,
                'userType' => $userType,
                'showTrade' => false,
                'showFollow' => false,
                'showAlreadyTraded' => false,
                'isAbleFilter' => false,
                'receivingAds' => false,
                't' => time()
            ];
            $response = Http::withOptions(['verify' => false])->timeout(5)->get("https://www.okx.com/v3/c2c/tradingOrders/books", $data);
            if ($response->successful()) {
                $result = json_decode($response->body(), true);
                if (isset($result['code']) && $result['code'] == 0) {
                    if (isset($result['data']) && isset($result['data'][$side]) && !empty($result['data'][$side])) {
                        return $result['data'][$side];
                    }
                }
            }
            return [];
        });
    }
}
