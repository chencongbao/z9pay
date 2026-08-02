<?php

namespace App\Services\TransferOrder;

use App\Traits\ServiceTraits;

class TransferOrderOverSettingAmountNoticeTelegramConfirmService
{
    use ServiceTraits;

    private $amountMap = null;

    public function excute($order = null): bool
    {
        if (!$order) {
            return false;
        }

        $mid = intval($order->mid ?? 0);
        $amount = floatval($order->amount ?? 0);
        if ($mid <= 0 || $amount <= 0) {
            return false;
        }

        $confirmAmount = $this->confirmAmountMap()[$mid] ?? 0;

        return $confirmAmount > 0 && $amount >= $confirmAmount;
    }

    private function confirmAmountMap(): array
    {
        if (is_array($this->amountMap)) {
            return $this->amountMap;
        }

        $map = [];
        foreach ($this->settings() as $setting) {
            if (!is_array($setting)) {
                continue;
            }

            $amount = floatval($setting['value'] ?? 0);
            $mids = array_values(array_filter(array_map('intval', $setting['mids'] ?? [])));
            if ($amount <= 0 || empty($mids)) {
                continue;
            }

            // 同一商户多行配置时，达到任一阈值即可触发，所以取最低金额。
            foreach ($mids as $mid) {
                if ($mid <= 0) {
                    continue;
                }
                $map[$mid] = empty($map[$mid]) ? $amount : min($map[$mid], $amount);
            }
        }

        $this->amountMap = $map;

        return $this->amountMap;
    }

    private function settings(): array
    {
        $setting = bob_admin_setting('transfer_max_amount_notice_telegram_confirm') ?: [];

        if (is_string($setting)) {
            $setting = json_decode($setting, true) ?: [];
        }

        return is_array($setting) ? $setting : [];
    }
}
