<?php

namespace App\Services\MerchantPayment;

use App\Services\Cache\MerchantPayment\GetMerchantPaymentRateListService;
use App\Services\Cache\MerchantPayment\GetMerchantTransferBankRateService;
use App\Services\Enums\ErrorCodeEnum;
use App\Traits\ServiceResponseTrait;
use Illuminate\Support\Facades\App;

class MerchantOrderRateService
{
    use ServiceResponseTrait;

    public function fillDepositRate(array &$saveData): array
    {
        $merchantPayment = App::make(GetMerchantPaymentRateListService::class)->excute($saveData['mid'], $saveData['payment_id']);
        if (empty($merchantPayment)) {
            return $this->fail(trans("api.none_gateway_2"), '未设置通道费率，请联系客服', ErrorCodeEnum::SUBMIT_RATE_ERROR);
        }

        $merchantPaymentArr = $this->matchHighestRate($merchantPayment, $saveData['amount']);
        if (empty($merchantPaymentArr)) {
            return $this->fail(trans("api.none_gateway_1"), '未匹配到通道费率，请联系客服确认提交金额', ErrorCodeEnum::SUBMIT_RATE_ERROR);
        }

        $this->fillRateData($saveData, $merchantPaymentArr);

        return ['success' => true];
    }

    public function checkDepositRateConfigured(array $saveData): array
    {
        $merchantPayment = App::make(GetMerchantPaymentRateListService::class)->excute($saveData['mid'], $saveData['payment_id']);
        if (empty($merchantPayment)) {
            return $this->fail(trans("api.none_gateway_2"), '未设置通道费率，请联系客服', ErrorCodeEnum::SUBMIT_RATE_ERROR);
        }

        return ['success' => true];
    }

    public function fillDepositFinalRate(array &$saveData, int $channelId): array
    {
        $merchantPayment = App::make(GetMerchantPaymentRateListService::class)->excute($saveData['mid'], $saveData['payment_id']);
        if (empty($merchantPayment)) {
            return $this->fail(trans("api.none_gateway_2"), '未设置通道费率，请联系客服', ErrorCodeEnum::SUBMIT_RATE_ERROR);
        }

        $matchedRate = $channelId > 0 ? $this->matchDepositChannelRate($merchantPayment, $saveData, $channelId) : [];
        $source = '商户指定渠道代收费率';
        if (empty($matchedRate)) {
            $matchedRate = $this->matchDepositChannelRate($merchantPayment, $saveData, 0);
            $source = '商户所有渠道代收费率';
        }
        if (empty($matchedRate)) {
            $matchedRate = $this->matchHighestRate($merchantPayment, $saveData['amount']);
            $source = '商户主代收费率';
        }
        if (empty($matchedRate)) {
            return $this->fail(trans("api.none_gateway_1"), '未匹配到通道费率，请联系客服确认提交金额', ErrorCodeEnum::SUBMIT_RATE_ERROR);
        }

        $this->fillRateData($saveData, $matchedRate);

        return ['success' => true, 'source' => $source];
    }

    public function fillDepositChannelRate(array &$saveData, int $channelId): bool
    {
        if ($channelId <= 0) {
            return false;
        }

        $merchantPayment = App::make(GetMerchantPaymentRateListService::class)->excute($saveData['mid'], $saveData['payment_id']);
        if (empty($merchantPayment)) {
            return false;
        }

        $matchedRate = $this->matchDepositChannelRate($merchantPayment, $saveData, $channelId);
        if (empty($matchedRate)) {
            $matchedRate = $this->matchDepositChannelRate($merchantPayment, $saveData, 0);
        }

        if (empty($matchedRate)) {
            return false;
        }

        $this->fillRateData($saveData, $matchedRate);

        return true;
    }

    private function matchDepositChannelRate(array $merchantPayment, array $saveData, int $channelId): array
    {
        $matchedRate = [];
        foreach ($merchantPayment as $paymentRate) {
            foreach ($this->normalizeRates($paymentRate['transfer_rates'] ?? []) as $channelRate) {
                $channelRate = (array)$channelRate;
                if ((int)($channelRate['channel_id'] ?? 0) !== $channelId) {
                    continue;
                }
                if (!$this->isDefaultRate($channelRate) && !$this->isAmountMatched($channelRate, $saveData['amount'])) {
                    continue;
                }

                $matchedRate = $this->pickHigherRate($matchedRate, $channelRate);
            }
        }

        return $matchedRate;
    }

    public function fillTransferRate(array &$saveData): array
    {
        $merchantPayment = App::make(GetMerchantPaymentRateListService::class)->excute($saveData['mid'], 7);
        if (empty($merchantPayment)) {
            return $this->fail(trans("api.none_transfer_2"), '未设置代付费率,请联系客服', ErrorCodeEnum::SUBMIT_RATE_ERROR);
        }

        $merchantPaymentArr = $this->matchHighestRate($merchantPayment, $saveData['amount']);
        if (empty($merchantPaymentArr)) {
            return $this->fail(trans("api.none_transfer_1"), '未匹配到代付费率,请联系客服确认代付金额', ErrorCodeEnum::SUBMIT_RATE_ERROR);
        }

        $bankRate = $this->matchTransferBankRate($saveData);
        if (!empty($bankRate)) {
            $merchantPaymentArr = $bankRate;
        }

        $this->fillRateData($saveData, $merchantPaymentArr);

        return ['success' => true];
    }

    public function checkTransferRateConfigured(array $saveData): array
    {
        $merchantPayment = App::make(GetMerchantPaymentRateListService::class)->excute($saveData['mid'], 7);
        if (empty($merchantPayment)) {
            return $this->fail(trans("api.none_transfer_2"), '未设置代付费率,请联系客服', ErrorCodeEnum::SUBMIT_RATE_ERROR);
        }

        return ['success' => true];
    }

    public function fillTransferFinalRate(array &$saveData, int $channelId): array
    {
        $merchantPayment = App::make(GetMerchantPaymentRateListService::class)->excute($saveData['mid'], 7);
        if (empty($merchantPayment)) {
            return $this->fail(trans("api.none_transfer_2"), '未设置代付费率,请联系客服', ErrorCodeEnum::SUBMIT_RATE_ERROR);
        }

        $matchedRate = $channelId > 0 ? $this->matchTransferBankRate($saveData, $channelId) : [];
        $source = '商户指定渠道银行代付费率';
        if (empty($matchedRate)) {
            $matchedRate = $this->matchTransferBankRate($saveData, 0);
            $source = '商户所有渠道银行代付费率';
        }
        if (empty($matchedRate)) {
            $matchedRate = $this->matchHighestRate($merchantPayment, $saveData['amount']);
            $source = '商户主代付费率';
        }
        if (empty($matchedRate)) {
            return $this->fail(trans("api.none_transfer_1"), '未匹配到代付费率,请联系客服确认代付金额', ErrorCodeEnum::SUBMIT_RATE_ERROR);
        }

        $this->fillRateData($saveData, $matchedRate);

        return ['success' => true, 'source' => $source];
    }

    public function fillTransferChannelBankRate(array &$saveData, int $channelId): bool
    {
        if ($channelId <= 0) {
            return false;
        }

        $bankRate = $this->matchTransferBankRate($saveData, $channelId);
        if (empty($bankRate)) {
            return false;
        }

        $this->fillRateData($saveData, $bankRate);

        return true;
    }

    private function matchHighestRate($rates, $amount): array
    {
        $matchedRate = [];

        foreach ($rates as $rate) {
            $rate = (array)$rate;
            if ($this->isDefaultRate($rate) || $this->isAmountMatched($rate, $amount)) {
                $matchedRate = $this->pickHigherRate($matchedRate, $rate);
            }
        }

        return $matchedRate;
    }

    private function matchTransferBankRate(array $saveData, int $channelId = 0): array
    {
        $bankRates = App::make(GetMerchantTransferBankRateService::class)->excute($saveData['mid']);
        if (empty($bankRates)) {
            return [];
        }

        $matchedRate = $this->matchTransferBankRateByChannel($bankRates, $saveData, $channelId);
        if (empty($matchedRate) && $channelId > 0) {
            $matchedRate = $this->matchTransferBankRateByChannel($bankRates, $saveData, 0);
        }

        return $matchedRate;
    }

    private function matchTransferBankRateByChannel(array $bankRates, array $saveData, int $channelId): array
    {
        $matchedRate = [];
        foreach ($bankRates as $bankRate) {
            $bankRate = (array)$bankRate;
            if ((int)($bankRate['bank_id'] ?? 0) !== (int)$saveData['bank_id']) {
                continue;
            }
            if (!$this->isSameRateChannel($bankRate, $channelId)) {
                continue;
            }
            if (!$this->isDefaultRate($bankRate) && !$this->isAmountMatched($bankRate, $saveData['amount'])) {
                continue;
            }

            $matchedRate = $this->pickHigherRate($matchedRate, $bankRate);
        }

        return $matchedRate;
    }

    private function isSameRateChannel(array $rate, int $channelId): bool
    {
        $rateChannelId = (int)($rate['channel_id'] ?? 0);

        return $channelId > 0 ? $rateChannelId === $channelId : $rateChannelId === 0;
    }

    private function isDefaultRate(array $rate): bool
    {
        return floatval($rate['min_limit_amount'] ?? 0) == 0 && floatval($rate['max_limit_amount'] ?? 0) == 0;
    }

    private function isAmountMatched(array $rate, $amount): bool
    {
        return floatval($rate['min_limit_amount'] ?? 0) <= floatval($amount)
            && floatval($amount) <= floatval($rate['max_limit_amount'] ?? 0);
    }

    private function pickHigherRate(array $current, array $rate): array
    {
        if (empty($current) || floatval($rate['pay_rate'] ?? 0) > floatval($current['pay_rate'] ?? 0)) {
            return $this->rateData($rate);
        }

        return $current;
    }

    private function rateData(array $rate): array
    {
        return [
            'pay_rate' => $rate['pay_rate'],
            'agent1_rate' => $rate['agent1_rate'],
            'agent2_rate' => $rate['agent2_rate'],
            'agent3_rate' => $rate['agent3_rate'],
        ];
    }

    private function normalizeRates($rates): array
    {
        if (empty($rates)) {
            return [];
        }

        if (is_string($rates)) {
            $rates = json_decode($rates, true);
        }

        if (!is_array($rates)) {
            return [];
        }

        return array_values(array_filter(array_map(function ($rate) {
            if (is_object($rate)) {
                $rate = (array)$rate;
            }

            return is_array($rate) ? $rate : null;
        }, $rates)));
    }

    private function fillRateData(array &$saveData, array $rate): void
    {
        $saveData['merchant_rate'] = floatval($rate['pay_rate']) / 100;
        $saveData['merchant_agent1_rate'] = floatval($rate['agent1_rate']) / 100;
        $saveData['merchant_agent2_rate'] = floatval($rate['agent2_rate']) / 100;
        $saveData['merchant_agent3_rate'] = floatval($rate['agent3_rate']) / 100;
    }
}
