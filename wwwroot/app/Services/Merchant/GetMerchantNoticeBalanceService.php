<?php

namespace App\Services\Merchant;

use App\Traits\ServiceTraits;

class GetMerchantNoticeBalanceService
{
    use ServiceTraits;

    public function excute($mid = 0)
    {
        return $this->getRule($mid)['value'];
    }

    public function getRule($mid = 0): array
    {
        $mid = intval($mid);
        $rule = [
            'compare' => 'lt',
            'value' => 0,
            'enabled' => false,
        ];

        $settings = bob_admin_setting('telegram_merchant_balance_notice_single');
        if (!empty($settings)) {
            foreach (json_decode($settings, true) ?: [] as $item) {
                if (intval($item['mid'] ?? 0) !== $mid) {
                    continue;
                }

                if (!array_key_exists('value', $item) || $item['value'] === '') {
                    break;
                }

                $compare = $item['compare'] ?? 'lt';
                $rule = [
                    'compare' => in_array($compare, ['lt', 'gt'], true) ? $compare : 'lt',
                    'value' => floatval($item['value'] ?? 0),
                    'enabled' => true,
                ];
                break;
            }
        }

        return $rule;
    }

    public function shouldNotice($availableBalance, array $rule): bool
    {
        if (empty($rule['enabled'])) {
            return false;
        }

        $value = floatval($rule['value'] ?? 0);
        if (($rule['compare'] ?? 'lt') === 'gt') {
            return floatval($availableBalance) > $value;
        }

        return floatval($availableBalance) < $value;
    }

    public function compareText(array $rule): string
    {
        return ($rule['compare'] ?? 'lt') === 'gt' ? '大于金额通知' : '小于金额通知';
    }
}
