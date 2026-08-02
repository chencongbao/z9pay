<?php

namespace App\Services\Telegram;

use App\Traits\ServiceTraits;

class GetMerchantTelegramTranslateLangService
{
    use ServiceTraits;

    private $items = [];

    public function excute($mid = 0): string
    {
        $mid = intval($mid);
        if ($mid <= 0) {
            return '';
        }

        if (isset($this->items[$mid])) {
            return $this->items[$mid];
        }

        $this->items[$mid] = $this->langMap()[$mid] ?? '';

        return $this->items[$mid];
    }

    private function langMap(): array
    {
        if (isset($this->items['lang_map'])) {
            return $this->items['lang_map'];
        }

        $items = $this->configItems();
        $map = [];

        foreach ($items as $key => $value) {
            if (!is_array($value)) {
                continue;
            }

            $lang = trim((string)($value['lang'] ?? (is_string($key) ? $key : '')));
            $mids = $value['mids'] ?? [];
            if ($lang === '' || !is_array($mids)) {
                continue;
            }

            // 配置表单已限制商户不能重复；这里保留后写覆盖，兼容历史脏数据。
            foreach (array_values(array_filter(array_map('intval', $mids))) as $mid) {
                $map[$mid] = $lang;
            }
        }

        $this->items['lang_map'] = $map;

        return $this->items['lang_map'];
    }

    private function configItems(): array
    {
        $items = bob_admin_setting('telegram_merchant_group_lang_config');
        if (empty($items)) {
            $items = bob_admin_setting('telegram_lang_traslate') ?: [];
        }

        if (is_string($items)) {
            $items = json_decode($items, true) ?: [];
        }

        return is_array($items) ? $items : [];
    }
}
