<?php

namespace App\Services\TransferOrder;

use App\Traits\ServiceTraits;
use Illuminate\Support\Facades\Http;
use Throwable;


//商户反查确认
class MerchantOrderQueryConfirmService
{
    use ServiceTraits;

    private const TIMEOUT_SECONDS = 5;

    private array $httpResult = [];

    public function excute($mid = 0, $data = [])
    {
        $methodname = preg_replace('/[^A-Za-z0-9_]/', '', config('app.name') . $mid);
        if (!method_exists($this, $methodname)) {
            $this->httpResult = [
                '是否请求HTTP' => '否',
                '失败原因' => '未配置商户反查方法',
                '商户ID' => $mid,
                '方法名' => $methodname,
            ];
            return false;
        }

        return $this->$methodname($data);
    }

    public function httpResult(): array
    {
        return $this->httpResult;
    }


    //BB777
    protected function sgpay366($data = [])
    {
        return $this->confirmByPostFixedUrl($data, "http://pay.yyoilo.com/api/forehead/fund/withdraw/queryGeneral", "366");
    }

    //YY777
    protected function sgpay365($data = [])
    {
        return $this->confirmByPostFixedUrl($data, "http://pay.milegame9.com/api/forehead/fund/withdraw/queryGeneral", "365");
    }

    protected function sgpay439($data = [])
    {
        return $this->confirmByGetWithdrawUrl($data);
    }

    protected function xinpay28($data = [])
    {
        return $this->confirmByPostWithdrawUrl($data, 28);
    }

    protected function xinpay27($data = [])
    {
        return $this->confirmByPostWithdrawUrl($data, 27);
    }

    protected function epay120($data = [])
    {
        return $this->confirmByPostFixedUrl($data, "http://pay.milegame9.com/api/forehead/fund/withdraw/queryGeneral", 120);
    }

    protected function epay121($data = [])
    {
        return $this->confirmByPostFixedUrl($data, "http://pay.yyoilo.com/api/forehead/fund/withdraw/queryGeneral", 121);
    }

    protected function epay154($data = [])
    {
        return $this->confirmByGetWithdrawUrl($data);
    }


    protected function luckypay1($data = [])
    {
        return $this->confirmByGetWithdrawUrl($data);
    }

    private function confirmByGetWithdrawUrl(array $data): bool
    {
        if (empty($data) || empty($data['withdrawQueryUrl'])) {
            return true;
        }

        $response = $this->get($data['withdrawQueryUrl'], [
            'amount' => $data['amount'],
            'orderId' => $data['order_no'],
            'address' => $data['card_no'],
        ]);

        return $this->responseCodeEquals($response, 10000);
    }

    private function confirmByPostWithdrawUrl(array $data, int $merchantCode): bool
    {
        if (empty($data) || empty($data['withdrawQueryUrl'])) {
            return true;
        }

        $response = $this->postForm($data['withdrawQueryUrl'], [
            'merchantCode' => $merchantCode,
            'orderId' => $data['order_no'],
            'bankCardNo' => $data['card_no'],
        ]);

        return $this->responseCodeEquals($response, 1);
    }

    private function confirmByPostFixedUrl(array $data, string $url, $merchantCode): bool
    {
        if (empty($data)) {
            return false;
        }

        $response = $this->postForm($url, [
            'merchantCode' => $merchantCode,
            'orderId' => $data['order_no'],
            'bankCardNo' => $data['card_no'],
        ]);

        return $this->responseCodeEquals($response, 1);
    }

    private function responseCodeEquals($response, $successCode): bool
    {
        $passed = $response && $response->successful() && isset($response['code']) && (string)$response['code'] === (string)$successCode;
        $this->httpResult = array_merge($this->httpResult, [
            '期望业务状态码' => $successCode,
            '实际业务状态码' => $response['code'] ?? null,
            '反查结果' => $passed ? '通过' : '未通过',
        ]);

        return $passed;
    }

    private function get(string $url, array $params)
    {
        return $this->request('GET', $url, $params);
    }

    private function postForm(string $url, array $params)
    {
        return $this->request('POST', $url, $params);
    }

    private function request(string $method, string $url, array $params)
    {
        $this->httpResult = [
            '是否请求HTTP' => '是',
            '请求方式' => $method,
            '请求地址' => $url,
            '请求参数' => $params,
            '请求超时秒数' => self::TIMEOUT_SECONDS,
        ];

        try {
            $response = $method === 'GET'
                ? Http::timeout(self::TIMEOUT_SECONDS)->get($url, $params)
                : Http::timeout(self::TIMEOUT_SECONDS)->asForm()->post($url, $params);

            $this->httpResult = array_merge($this->httpResult, [
                'HTTP状态码' => $response->status(),
                'HTTP是否成功' => $response->successful() ? '是' : '否',
                '响应JSON' => $response->json(),
                '响应原文' => $this->limitText($response->body()),
            ]);

            return $response;
        } catch (Throwable $e) {
            $this->httpResult = array_merge($this->httpResult, [
                'HTTP状态码' => null,
                'HTTP是否成功' => '否',
                '异常类型' => get_class($e),
                '异常信息' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function limitText($text): string
    {
        $text = (string)$text;
        if (mb_strlen($text) <= 2000) {
            return $text;
        }

        return mb_substr($text, 0, 2000) . '...';
    }
}
