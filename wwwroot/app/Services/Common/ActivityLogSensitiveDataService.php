<?php

namespace App\Services\Common;

class ActivityLogSensitiveDataService
{
    public const MASK_VALUE = '******';

    private const DROP_KEYS = ['_token'];

    private const EXACT_MASK_KEYS = [
        'api_secret',
        'api_token',
        'app_secret',
        'appkey',
        'appsecret',
        'amount_password',
        'authorization',
        'captcha',
        'google_2fa_code',
        'google_two_fa_code',
        'google_two_fa_secret',
        'key',
        'pay_password',
        'password',
        'password_confirm',
        'password_confirmation',
        'passwordconfirm',
        'private_key',
        'privatekey',
        'remember_token',
        'secret',
        'sign',
        'signature',
        'telegram_bot_token',
        'token',
    ];

    private const MASK_KEYWORDS = [
        'authorization',
        'appkey',
        'appsecret',
        'password',
        'private key',
        'private_key',
        'privatekey',
        'secret',
        'token',
    ];

    public function sanitizeArray(array $data, bool $dropTokenFields = false, bool &$changed = null): array
    {
        $changed = false;

        return $this->sanitizeArrayInternal($data, $dropTokenFields, $changed);
    }

    public function sanitizeJsonColumn($value, bool $dropTokenFields = false): array
    {
        if ($value === null || $value === '') {
            return [null, false];
        }

        $decoded = json_decode((string)$value, true);
        if (!is_array($decoded)) {
            return [null, false];
        }

        $changed = false;
        $masked = $this->sanitizeArrayInternal($decoded, $dropTokenFields, $changed);

        return [$changed ? json_encode($masked, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null, $changed];
    }

    public function sanitizeDescription(string $description): string
    {
        $patterns = [
            '/(密码[:：])\\s*([^|\\s]+)/u',
            '/(password\\s*[:：=])\\s*([^|\\s]+)/iu',
            '/(pay_password\\s*[:：=])\\s*([^|\\s]+)/iu',
            '/(amount_password\\s*[:：=])\\s*([^|\\s]+)/iu',
        ];

        foreach ($patterns as $pattern) {
            $description = preg_replace($pattern, '$1' . self::MASK_VALUE, $description) ?? $description;
        }

        return $description;
    }

    private function sanitizeArrayInternal(array $data, bool $dropTokenFields, bool &$changed): array
    {
        foreach ($data as $key => $value) {
            $normalizedKey = $this->normalizeKey($key);
            if ($dropTokenFields && in_array($normalizedKey, self::DROP_KEYS, true)) {
                unset($data[$key]);
                $changed = true;
                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->sanitizeArrayInternal($value, $dropTokenFields, $changed);
                continue;
            }

            if ($this->shouldMaskKey($normalizedKey) && (string)$value !== self::MASK_VALUE) {
                $data[$key] = self::MASK_VALUE;
                $changed = true;
            }
        }

        return $data;
    }

    private function shouldMaskKey(string $key): bool
    {
        if (in_array($key, self::DROP_KEYS, true) || in_array($key, self::EXACT_MASK_KEYS, true)) {
            return true;
        }

        foreach (self::MASK_KEYWORDS as $keyword) {
            if (str_contains($key, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeKey($key): string
    {
        return strtolower(str_replace('-', '_', trim((string)$key)));
    }
}
