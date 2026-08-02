<?php

namespace App\Services\TransferOrder;

use App\Traits\ServiceTraits;

class CheckChannelTransferPaymentService
{
    use ServiceTraits;

    private const BANK_CARD = '0';
    private const ALIPAY = '93';
    private const WECHAT = '84';
    private const DIGITAL_CNY = '175';

    public function excute($bank_id = 0, $transfer_payment = ""): bool
    {
        $allowedTransferPayments = $this->parseTransferPayments($transfer_payment);
        if (empty($allowedTransferPayments)) {
            return true;
        }

        return in_array($this->transferPaymentId($bank_id), $allowedTransferPayments, true);
    }

    private function transferPaymentId($bank_id): string
    {
        $bankId = (string)$bank_id;

        return match ($bankId) {
            self::ALIPAY => self::ALIPAY,
            self::WECHAT => self::WECHAT,
            self::DIGITAL_CNY => self::DIGITAL_CNY,
            default => self::BANK_CARD,
        };
    }

    private function parseTransferPayments($transferPayment): array
    {
        if ($transferPayment === null || $transferPayment === '') {
            return [];
        }

        if (is_array($transferPayment)) {
            $items = $transferPayment;
        } else {
            $items = explode(',', (string)$transferPayment);
        }

        return array_values(array_unique(array_filter(array_map(function ($item) {
            return trim((string)$item);
        }, $items), fn($item) => $item !== '')));
    }
}
