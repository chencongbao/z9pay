<?php

namespace App\Services\Telegram;

class MerchantBotOrderLookupRuleService
{
    public function hasMerchantConfiguration(int $merchantId): bool
    {
        return $this->hasMerchantConfigurationInRows($merchantId, $this->settingRows());
    }

    public function hasMerchantConfigurationInRows(int $merchantId, array $items): bool
    {
        if ($merchantId <= 0) {
            return false;
        }

        foreach ($items as $item) {
            if (is_array($item) && intval($item['mid'] ?? 0) === $merchantId) {
                return true;
            }
        }

        return false;
    }

    public function extractOrderNumbers(int $merchantId, string $text): array
    {
        if ($merchantId <= 0 || trim($text) === '') {
            return [];
        }

        return $this->extractOrderNumbersFromRows($merchantId, $text, $this->settingRows());
    }

    public function extractOrderNumbersFromRows(int $merchantId, string $text, array $items): array
    {
        if ($merchantId <= 0 || trim($text) === '') {
            return [];
        }

        $matches = [];
        foreach ($this->enabledRules($merchantId, $items) as $rule) {
            $pattern = $this->toPattern($rule);
            if ($pattern === '') {
                continue;
            }

            if (@preg_match_all($pattern, $text, $result) !== false) {
                foreach ($this->matchedValues($result) as $value) {
                    $matches[] = $value;
                }
            }
        }

        return array_values(array_unique(array_filter($matches)));
    }

    public function isValidRule(string $rule): bool
    {
        $pattern = $this->toPattern($rule);

        return $pattern !== '' && @preg_match($pattern, '') !== false;
    }

    private function enabledRules(int $merchantId, array $items): array
    {
        $rules = [];

        foreach ($items as $item) {
            if (intval($item['status'] ?? 0) !== 1 || intval($item['mid'] ?? 0) !== $merchantId) {
                continue;
            }

            foreach (preg_split('/\r\n|\r|\n/', (string)($item['order_no_rules'] ?? '')) ?: [] as $rule) {
                $rule = trim($rule);
                if ($rule !== '') {
                    $rules[] = $rule;
                }
            }
        }

        return $rules;
    }

    private function settingRows(): array
    {
        $items = bob_admin_setting('telegram_merchant_bot_order_lookup_rules') ?: [];
        if (is_string($items)) {
            $decoded = json_decode($items, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($items) ? $items : [];
    }

    private function toPattern(string $rule): string
    {
        $rule = trim($rule);
        if ($rule === '') {
            return '';
        }

        if ($this->isDelimitedPattern($rule)) {
            return $rule;
        }

        return '/' . str_replace('/', '\/', $rule) . '/u';
    }

    private function isDelimitedPattern(string $rule): bool
    {
        $delimiter = $rule[0] ?? '';
        if ($delimiter === '' || ctype_alnum($delimiter) || $delimiter === '\\') {
            return false;
        }

        return preg_match('/^' . preg_quote($delimiter, '/') . '.+' . preg_quote($delimiter, '/') . '[a-zA-Z]*$/', $rule) === 1;
    }

    private function matchedValues(array $result): array
    {
        $source = !empty($result[1]) ? $result[1] : ($result[0] ?? []);

        return array_map(fn ($value) => trim((string)$value), $source);
    }
}
