<?php

namespace App\Services\SystemNotice;

use Illuminate\Support\Facades\Cache;

class SystemNoticeService
{
    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';

    const DEFAULT_TTL_SECONDS = 60;

    private const DEFAULT_ENABLED = [
        'merchant_sign_error' => true,
        'transfer_check_failed' => true,
        'deposit_order_confirm_pay_failed' => true,
        'ip_debug' => false,
    ];

    public function debug(string $code, $message, int $ttlSeconds = self::DEFAULT_TTL_SECONDS, ?int $mid = null): bool
    {
        return $this->send($code, $message, self::LEVEL_DEBUG, $ttlSeconds, $mid);
    }

    public function info(string $code, $message, int $ttlSeconds = self::DEFAULT_TTL_SECONDS, ?int $mid = null): bool
    {
        return $this->send($code, $message, self::LEVEL_INFO, $ttlSeconds, $mid);
    }

    public function warning(string $code, $message, int $ttlSeconds = self::DEFAULT_TTL_SECONDS, ?int $mid = null): bool
    {
        return $this->send($code, $message, self::LEVEL_WARNING, $ttlSeconds, $mid);
    }

    public function error(string $code, $message, int $ttlSeconds = self::DEFAULT_TTL_SECONDS, ?int $mid = null): bool
    {
        return $this->send($code, $message, self::LEVEL_ERROR, $ttlSeconds, $mid);
    }

    public function send(string $code, $message, string $level = self::LEVEL_WARNING, int $ttlSeconds = self::DEFAULT_TTL_SECONDS, ?int $mid = null): bool
    {
        $level = $this->normalizeLevel($level);
        if (!$this->shouldSend($code, $level, $mid)) {
            return false;
        }

        $payload = $this->payload($code, $level, $message, $mid);
        if (!$this->acquireNoticeLock($code, $level, $payload, $ttlSeconds)) {
            return false;
        }

        bob_send_system_error_message($payload);

        return true;
    }

    public function enable(string $code, ?int $mid = null): void
    {
        Cache::forever($this->enabledKey($code, $mid), 1);
    }

    public function disable(string $code, ?int $mid = null): void
    {
        Cache::forever($this->enabledKey($code, $mid), 0);
    }

    public function enabled(string $code, ?int $mid = null, ?string $level = null): bool
    {
        $key = $this->enabledKey($code, $mid);
        $value = Cache::get($key);
        if ($value !== null) {
            return $value == 1;
        }

        $legacyValue = $this->legacyEnabledValue($code, $mid);
        if (!is_null($legacyValue)) {
            return $legacyValue == 1;
        }

        return $this->defaultEnabled($code, $level);
    }

    public function shouldSend(string $code, string $level, ?int $mid = null): bool
    {
        $level = $this->normalizeLevel($level);

        return $this->enabled($code, $mid, $level);
    }

    private function normalizeLevel(string $level): string
    {
        $level = strtolower($level);
        if (in_array($level, [self::LEVEL_DEBUG, self::LEVEL_INFO, self::LEVEL_WARNING, self::LEVEL_ERROR], true)) {
            return $level;
        }

        return self::LEVEL_WARNING;
    }

    private function payload(string $code, string $level, $message, ?int $mid = null): array
    {
        $payload = is_array($message)
            ? $message
            : ['message' => (string) $message];

        $base = [
            'notice_code' => $code,
            'notice_level' => $level,
            'time' => now()->format('Y-m-d H:i:s'),
        ];

        if ($mid !== null) {
            $base['notice_mid'] = $mid;
        }

        return array_merge($base, $payload);
    }

    private function acquireNoticeLock(string $code, string $level, array $payload, int $ttlSeconds): bool
    {
        if ($ttlSeconds <= 0) {
            return true;
        }

        $dedupePayload = $payload;
        unset($dedupePayload['time']);

        $noticeKey = 'system_notice:sent:' . $level . ':' . $code . ':' . md5(json_encode($dedupePayload, JSON_UNESCAPED_UNICODE));

        return Cache::add($noticeKey, 1, now()->addSeconds($ttlSeconds));
    }

    private function enabledKey(string $code, ?int $mid = null): string
    {
        if ($mid !== null && $mid > 0) {
            return 'system_notice:merchant:' . $mid . ':' . $code;
        }

        return 'system_notice:enabled:' . $code;
    }

    private function defaultEnabled(string $code, ?string $level = null): bool
    {
        if (array_key_exists($code, self::DEFAULT_ENABLED)) {
            return self::DEFAULT_ENABLED[$code];
        }

        $level = $level ? $this->normalizeLevel($level) : null;

        return in_array($level, [self::LEVEL_WARNING, self::LEVEL_ERROR], true);
    }

    private function legacyEnabledValue(string $code, ?int $mid)
    {
        if ($code !== 'merchant_sign_error' || $mid === null || $mid <= 0) {
            return null;
        }

        $legacyKey = 'merchant_sign_error_notice_' . $mid;
        $value = Cache::get($legacyKey);
        if ($value === null) {
            return null;
        }

        return $value;
    }
}
