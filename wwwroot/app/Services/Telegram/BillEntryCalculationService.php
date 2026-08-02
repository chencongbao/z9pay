<?php

namespace App\Services\Telegram;

use InvalidArgumentException;

class BillEntryCalculationService
{
    public const CURRENCY_CNY = 'CNY';
    public const CURRENCY_USDT = 'USDT';

    public function calculate(float $originalAmount, string $originalCurrency, float $exchangeRate, float $feeRate, bool $isIncome): array
    {
        if ($originalAmount <= 0) {
            throw new InvalidArgumentException('记账金额必须大于0');
        }
        if (!in_array($originalCurrency, [self::CURRENCY_CNY, self::CURRENCY_USDT], true)) {
            throw new InvalidArgumentException('记账币种无效');
        }
        if ($originalCurrency === self::CURRENCY_USDT && $exchangeRate <= 0) {
            throw new InvalidArgumentException('请先设置大于0的汇率');
        }
        if ($feeRate < 0 || $feeRate > 100) {
            throw new InvalidArgumentException('费率必须在0到100之间');
        }

        $amount = $originalCurrency === self::CURRENCY_USDT
            ? $this->roundAmount($originalAmount * $exchangeRate)
            : $this->roundAmount($originalAmount);
        $payableAmount = $isIncome ? $this->roundAmount($amount * (100 - $feeRate) / 100) : null;

        return [
            'amount' => $amount,
            'original_amount' => round($originalAmount, 6, PHP_ROUND_HALF_UP),
            'original_currency' => $originalCurrency,
            'exchange_rate' => round($exchangeRate, 6, PHP_ROUND_HALF_UP),
            'fee_rate' => round($feeRate, 6, PHP_ROUND_HALF_UP),
            'payable_amount' => $payableAmount,
            'calculation_version' => 1,
        ];
    }

    private function roundAmount(float $amount): float
    {
        return round($amount, 2, PHP_ROUND_HALF_UP);
    }
}
