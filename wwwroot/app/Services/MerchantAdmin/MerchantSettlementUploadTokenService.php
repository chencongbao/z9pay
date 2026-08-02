<?php

namespace App\Services\MerchantAdmin;

use Dcat\Admin\Admin;
use RuntimeException;

class MerchantSettlementUploadTokenService
{
    private const SESSION_KEY = 'merchant_settlement_excel_uploads';

    private const SESSION_ID_KEY = 'merchant_settlement_excel_upload_session_id';

    private const TTL_SECONDS = 3600;

    private const MAX_ITEMS = 20;

    public function register(string $path): void
    {
        $path = $this->normalizePath($path);
        $items = $this->activeItems();
        $items[$path] = time() + self::TTL_SECONDS;
        if (count($items) > self::MAX_ITEMS) {
            asort($items);
            $items = array_slice($items, -self::MAX_ITEMS, null, true);
        }

        session()->put($this->sessionKey(), $items);
    }

    public function assertUsable(string $path): void
    {
        $path = $this->normalizePath($path);
        $items = $this->activeItems();
        if (!isset($items[$path])) {
            throw new RuntimeException($this->message('upload_file_expired'));
        }
    }

    public function consume(string $path): void
    {
        $path = $this->normalizePath($path);
        $items = $this->activeItems();
        unset($items[$path]);
        session()->put($this->sessionKey(), $items);
    }

    private function activeItems(): array
    {
        $now = time();
        $items = (array)session()->get($this->sessionKey(), []);
        $items = array_filter($items, fn ($expiresAt) => (int)$expiresAt > $now);
        session()->put($this->sessionKey(), $items);

        return $items;
    }

    private function normalizePath(string $path): string
    {
        return ltrim(str_replace('\\', '/', trim($path)), '/');
    }

    private function sessionKey(): string
    {
        return self::SESSION_KEY . '.' . (int)(Admin::user()?->id ?? 0) . '.' . $this->sessionToken();
    }

    private function sessionToken(): string
    {
        $token = (string)session()->get(self::SESSION_ID_KEY, '');
        if ($token === '') {
            $token = session()->getId();
            session()->put(self::SESSION_ID_KEY, $token);
        }

        return $token;
    }

    private function message(string $key): string
    {
        return __("handle-form.fields.{$key}");
    }
}
