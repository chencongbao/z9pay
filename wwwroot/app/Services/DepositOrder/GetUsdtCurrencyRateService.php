<?php

namespace App\Services\DepositOrder;

use Throwable;
use RuntimeException;
use App\Traits\ServiceTraits;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use App\Services\Enums\OkxCurrencyEnum;
use App\Services\Common\ReportExceptionService;
use App\Services\Cache\Merchant\CacheMerchantBaseInfoService;

class GetUsdtCurrencyRateService
{
    use ServiceTraits;

    private const CONNECT_TIMEOUT_SECONDS = 3;
    private const REQUEST_TIMEOUT_SECONDS = 8;
    private const REQUEST_RETRIES = 2;
    private const RETRY_SLEEP_MILLISECONDS = 200;

    public function excute($currency_id = 0, $mid = 0)
    {
        $quoteCurrency = OkxCurrencyEnum::codeByCurrencyId((int) $currency_id);
        if (empty($quoteCurrency)) {
            return 0;
        }

        return $this->fetchByQuoteCurrency($quoteCurrency, $mid);
    }

    public function init($currency_id = 1, $okx_payment_method = null, $okx_user_type = null, $okx_side = null, $okx_index = 2)
    {
        $quoteCurrency = OkxCurrencyEnum::codeByCurrencyId((int) $currency_id) ?: OkxCurrencyEnum::CNY;

        return $this->getRate($okx_payment_method, $quoteCurrency, $okx_user_type, $okx_side, $okx_index);
    }

    protected function fetchByQuoteCurrency(string $quoteCurrency, $mid = 0)
    {
        $merchant_info = App::make(CacheMerchantBaseInfoService::class)->excute($mid);

        return $this->getRate(
            $merchant_info['okx_payment_method'] ?? 'bank',
            $quoteCurrency,
            $merchant_info['okx_user_type'] ?? 'all',
            $merchant_info['okx_side'] ?? 'sell',
            $merchant_info['okx_index'] ?? 2
        );
    }

    private function getRate($type, string $currency, $userType, $side, $index)
    {
        $type = trim((string) $type) ?: 'bank';
        $userType = trim((string) $userType) ?: 'all';
        $side = trim((string) $side) ?: 'sell';
        $index = $index === null || $index === '' ? 2 : max(0, (int) $index);
        $result = array_values($this->fetchData($type, $currency, $userType, $side));
        $rate = $result[$index]['price'] ?? 0;
        if (!$this->isValidRate($rate)) {
            return 0;
        }

        return $rate;
    }

    public function fetchData($type = 'all', $currency = 'CNY', $userType = 'all', $side = 'sell'): array
    {
        $data = [
            'quoteCurrency' => $currency,
            'baseCurrency' => 'USDT',
            'side' => $side,
            'paymentMethod' => $type,
            'userType' => $userType,
            'showTrade' => false,
            'showFollow' => false,
            'showAlreadyTraded' => false,
            'isAbleFilter' => false,
            'receivingAds' => false,
            't' => time(),
        ];

        try {
            $response = Http::connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
                ->timeout(self::REQUEST_TIMEOUT_SECONDS)
                ->retry(self::REQUEST_RETRIES, self::RETRY_SLEEP_MILLISECONDS, null, false)
                ->get('https://www.okx.com/v3/c2c/tradingOrders/books', $data);

            if (!$response->successful()) {
                $this->reportFailure('OKX费率接口响应异常', "HTTP {$response->status()}", $data, [
                    'response' => substr($response->body(), 0, 500),
                ]);
                return [];
            }

            $result = $response->json();
            if ((int) ($result['code'] ?? -1) === 0 && !empty($result['data'][$side]) && is_array($result['data'][$side])) {
                return $result['data'][$side];
            }

            $this->reportFailure('OKX费率接口数据无效', '未返回有效费率列表', $data, [
                'response_code' => $result['code'] ?? null,
                'response_message' => $result['msg'] ?? null,
            ]);
        } catch (Throwable $e) {
            app(ReportExceptionService::class)->report('抓取实时费率异常', $e, [
                'type' => $type,
                'currency' => $currency,
                'user_type' => $userType,
                'side' => $side,
            ]);
        }

        return [];
    }

    private function isValidRate($rate): bool
    {
        return is_numeric($rate) && (float) $rate > 0;
    }

    private function reportFailure(string $title, string $message, array $data, array $context = []): void
    {
        app(ReportExceptionService::class)->report($title, new RuntimeException($message), array_merge([
            'type' => $data['paymentMethod'] ?? null,
            'currency' => $data['quoteCurrency'] ?? null,
            'user_type' => $data['userType'] ?? null,
            'side' => $data['side'] ?? null,
        ], $context));
    }
}
