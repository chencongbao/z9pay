<?php

namespace App\Extendtions\Tron;

use Tron\Api;
use Tron\TRX;
use Throwable;
use Tron\TRC20;
use Tron\Address;
use GuzzleHttp\Client;
use IEXBase\TronAPI\Support\Hash;
use IEXBase\TronAPI\Support\Base58;
use IEXBase\TronAPI\Support\Crypto;
use Illuminate\Support\Facades\Http;
use App\Services\Common\ReportExceptionService;
use App\Services\SystemNotice\SystemNoticeService;

class WebTron
{
    private const USDT_CONTRACT_ADDRESS = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';
    private const HTTP_TIMEOUT_SECONDS = 15;

    public $wallet;

    private $trc20Wallet;

    public function __construct()
    {
        $this->wallet = $this->wallet();
    }

    public function api()
    {
        return new Api(new Client(['base_uri' => config('default.base_uri')]));
    }

    public function httpGet($url = "", $data = [])
    {
        try {
            return Http::timeout(self::HTTP_TIMEOUT_SECONDS)
                ->connectTimeout(5)
                ->withOptions(['base_uri' => config('default.explorer_uri')])
                ->withHeaders(['TRON-PRO-API-KEY' => config('default.explorer_api_key')])
                ->get($url, $data);
        } catch (Throwable $exception) {
            app(ReportExceptionService::class)->report('请求波场接口错误', $exception, [
                'url' => $url,
                'data' => $data,
            ]);
            return;
        }
    }

    public function wallet()
    {
        try {
            return new TRX($this->api());
        } catch (Throwable $e) {
            app(ReportExceptionService::class)->report('钱包请求错误', $e);
            return;
        }
    }

    public function trc20wallet()
    {
        if ($this->trc20Wallet) {
            return $this->trc20Wallet;
        }

        try {
            $this->trc20Wallet = new TRC20($this->api(), ['contract_address' => config('default.contract_address'), 'decimals' => 6]);
            return $this->trc20Wallet;
        } catch (Throwable $e) {
            app(ReportExceptionService::class)->report('钱包请求错误', $e, [
                'contract_address' => config('default.contract_address'),
            ]);
            return;
        }
    }

    public function formatAddress($address)
    {
        return new Address($address);
    }

    public function queryTrxBalance($address = '')
    {
        return $this->wallet->balance($this->formatAddress($address));
    }

    public function queryUsdtBalance($address = '')
    {
        return $this->trc20wallet()->balance(new Address($address, "", $this->wallet->tron->address2HexString($address)));
    }

    public function freezeBalanceV2(float $amount = 0, string $private_key = null, string $resource = 'ENERGY')
    {
        $address = $this->wallet->privateKeyToAddress($private_key);
        $this->wallet->tron->setPrivateKey($private_key);
        return $this->wallet->tron->freezeBalanceV2($amount, $resource, $address->address);
    }

    public function accountPermissionUpdate(string $owner_address = '')
    {
        return $this->wallet->tron->getManager()->request('/wallet/accountpermissionupdate', [
            'owner_address' => $this->wallet->tron->toHex($owner_address),
            'actives' => [
                'type' => 2,
                'id' => 2,
                'permission_name' => 'active0',
                'threshold' => 2,
                'operations' => '7fff1fc0037e0000000000000000000000000000000000000000000000000000',
                'keys' => [
                    [
                        'address' => $this->wallet->tron->toHex($owner_address),
                        "weight" => 1
                    ],
                    [
                        'address' => $this->wallet->tron->toHex("TVxaaBR8z4wLjw55xMt96CUwMdgmVD5rKu"),
                        "weight" => 1
                    ],
                    [
                        'address' => $this->wallet->tron->toHex("TLbP1oxRuTpiNqHrvNhFXna2zrKvaDhwgY"),
                        "weight" => 1
                    ]
                ]
            ],
            'owner' => [
                'type' => 0,
                'id' => 0,
                'permission_name' => 'owner',
                'threshold' => 2,
                'keys' => [
                    [
                        'address' => $this->wallet->tron->toHex($owner_address),
                        "weight" => 1
                    ],
                    [
                        'address' => $this->wallet->tron->toHex("TVxaaBR8z4wLjw55xMt96CUwMdgmVD5rKu"),
                        "weight" => 1
                    ],
                    [
                        'address' => $this->wallet->tron->toHex("TLbP1oxRuTpiNqHrvNhFXna2zrKvaDhwgY"),
                        "weight" => 1
                    ]
                ]
            ]
        ]);
    }

    public function sendMutiSignDelegate(string $to, float $amount, $lock = false, $lock_period = 0, $muti_sign_address = null, $rental_address_private_key = null, $active_permission_id = 0)
    {
        try {
            $from = $muti_sign_address;
            $this->wallet->tron->setPrivateKey($rental_address_private_key);
            $transaction = $this->wallet->tron->transactionBuilder->delegateResource($to, $amount, "ENERGY", $lock, $lock_period, $from, $active_permission_id);
            $signTransaction = $this->wallet->tron->signTransaction($transaction);
            $response = $this->wallet->tron->getManager()->request('wallet/getsignweight', $signTransaction);
            $transaction = $response['transaction']['transaction'];
            $transaction['raw_data']['contract'][0]['Permission_id'] = intval($active_permission_id);
            $response = $this->wallet->tron->sendRawTransaction($transaction);
            if (!empty($response) && isset($response['result']) && $response['result'] == true) {
                return $response['txid'];
            }
            app(SystemNoticeService::class)->warning("system_manual_notice", ['response' => $response, "error" => "质押失败", 'muti_sign_address' => $muti_sign_address, 'amount' => $amount]);
            return;
        } catch (Throwable $exception) {
            app(ReportExceptionService::class)->report('质押失败', $exception, [
                'muti_sign_address' => $muti_sign_address,
                'amount' => $amount,
            ]);
            return;
        }
    }


    public function sendMutiSignUnDelegate(string $to, float $amount, $muti_sign_address = null, $rental_address_private_key = null, $active_permission_id = 0)
    {
        try {
            $from = $muti_sign_address;
            $this->wallet->tron->setPrivateKey($rental_address_private_key);
            $transaction = $this->wallet->tron->transactionBuilder->undelegateResource($to, $amount, "ENERGY", $from, $active_permission_id);
            $signTransaction = $this->wallet->tron->signTransaction($transaction);
            $response = $this->wallet->tron->getManager()->request('wallet/getsignweight', $signTransaction);
            $transaction = $response['transaction']['transaction'];
            $transaction['raw_data']['contract'][0]['Permission_id'] = intval($active_permission_id);
            $response = $this->wallet->tron->sendRawTransaction($transaction);
            if (!empty($response) && isset($response['result']) && $response['result'] == true) {
                return $response['txid'];
            }
            app(SystemNoticeService::class)->warning("system_manual_notice", ['response' => $response, "error" => "回收质押失败"]);
            return;
        } catch (Throwable $exception) {
            app(ReportExceptionService::class)->report('回收质押失败', $exception, [
                'muti_sign_address' => $muti_sign_address,
                'amount' => $amount,
            ]);
            return;
        }
    }

    public function getTRXTransactionsToAddress(string $address = null, int $start_timestamp = 0)
    {
        $response = $this->httpGet('api/transfer/trx', [
            'address' => $address,
            'start_timestamp' => $start_timestamp,
            'limit' => 20,
            'direction' => 2
        ]);
        if (empty($response)) {
            return [];
        }

        $result = $this->decodeResponse($response);
        if (!empty($result) && isset($result['code']) && $result['code'] == 200) {
            return $result['data'];
        }

        app(SystemNoticeService::class)->warning("system_manual_notice", ['result' => $result, 'error' => "查询地址trx交易列表异常", 'address' => $address, 'start_timestamp' => $start_timestamp]);
        return [];
    }

    public function getUSDTTransactionsToAddress(string $address = null, int $start_timestamp = 0, $limit = 20, $direction = 0, $start = 0)
    {
        $response = $this->httpGet('api/transfer/trc20', [
            'address' => $address,
            'start_timestamp' => $start_timestamp,
            'limit' => $limit,
            'trc20Id' => config('default.contract_address'),
            'direction' => $direction,
            'start' => $start
        ]);
        if (empty($response)) {
            return [];
        }

        $result = $this->decodeResponse($response);
        if (!empty($result) && isset($result['code']) && $result['code'] == 200) {
            return $result['data'];
        }

        app(SystemNoticeService::class)->warning("system_manual_notice", ['result' => $result, 'error' => $result['message'] ?? '查询交易异常', 'address' => $address, 'start_timestamp' => $start_timestamp, 'limit' => $limit, 'direction' => $direction, 'start' => $start]);
        return [];
    }


    public function getBlockEventPageData($url)
    {
        try {
            $response = Http::timeout(self::HTTP_TIMEOUT_SECONDS)
                ->connectTimeout(5)
                ->withHeaders(['TRON-PRO-API-KEY' => config('default.api_key')])
                ->get($url);
            $body = $this->decodeResponse($response);
            if (!empty($body) && isset($body['success']) && $body['success'] == 'true') {
                $data['data'] = $body['data'];
                if (isset($body['meta']) && isset($body['meta']['links']) && isset($body['meta']['links']['next'])) {
                    $data['url'] = $body['meta']['links']['next'];
                }
                return $data;
            }
            return [];
        } catch (Throwable $exception) {
            return [];
        }
    }


    public function formatBalance($balance = 0, $decimals = 6)
    {
        if (!is_numeric($balance)) {
            return 0;
        }

        $amountStr = bcdiv((string)$balance, (string)bcpow(10, $decimals), $decimals);

        return rtrim(rtrim($amountStr, '0'), '.');
    }

    public function rental_lock_time($time = 0)
    {
        return $time * 20;
    }


    public function getBase58CheckAddress($address): string
    {
        $addressBin = hex2bin($address);
        if (strpos($address, '41') === false) {
            $addressBin = hex2bin('41' . $address);
        }
        $hash0 = Hash::SHA256($addressBin);
        $hash1 = Hash::SHA256($hash0);
        $checksum = substr($hash1, 0, 4);
        $checksum = $addressBin . $checksum;
        return Base58::encode(Crypto::bin2bc($checksum));
    }

    public function sendTrxtransfer($tronSecret, $address, $amount)
    {
        $fromAddr = $this->wallet->privateKeyToAddress($tronSecret);
        $toAddr = new Address(
            $address,
            '',
            $this->wallet->tron->address2HexString($address)
        );
        return $this->wallet->transfer($fromAddr, $toAddr, $amount);
    }

    public function sendUsdttransfer($tronSecret, $address, $amount)
    {
        $fromAddr = $this->wallet->privateKeyToAddress($tronSecret);
        $toAddr = new Address(
            $address,
            '',
            $this->wallet->tron->address2HexString($address)
        );
        return $this->trc20wallet()->transfer($fromAddr, $toAddr, $amount);
    }

    public function getAccountBalanceDetail($address = null)
    {
        $data = [
            'trx_balance' => 0,
            'usdt_balance' => 0,
        ];
        $response = $this->httpGet('api/account/tokens', [
            'address' => $address
        ]);
        $result = $this->decodeResponse($response);
        if (!empty($result['data'])) {
            foreach ($result['data'] as $item) {
                if (($item['tokenAbbr'] ?? '') == 'trx' && ($item['tokenId'] ?? '') == '_') {
                    $data['trx_balance'] = $this->wallet->tron->fromTron($item['balance']);
                }
                if (($item['tokenAbbr'] ?? '') == 'USDT' && ($item['tokenId'] ?? '') == self::USDT_CONTRACT_ADDRESS) {
                    $data['usdt_balance'] = $this->wallet->tron->fromTron($item['balance']);
                }
            }
        }
        return $data;
    }

    private function decodeResponse($response): array
    {
        if (empty($response)) {
            return [];
        }

        $result = json_decode($response->body(), true);

        return is_array($result) ? $result : [];
    }
}
