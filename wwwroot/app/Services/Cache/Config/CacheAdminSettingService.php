<?php

namespace App\Services\Cache\Config;

use App\Models\AdminSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use App\Services\Cache\CacheConstPrefixService;
use Illuminate\Contracts\Cache\LockTimeoutException;

class CacheAdminSettingService
{
    private ?array $settings = null;

    public function excute($name = '', $value = null, $isSet = false)
    {
        $key = CacheConstPrefixService::ADMIN_SETTING;
        if (is_array($name)) {
            admin_setting($name);
            $this->refreshSettings($key);

            return true;
        }

        if ($name === '' || $name === null) {
            return $this->getSettings($key);
        }

        if (is_string($name)) {
            if ($isSet) {
                admin_setting([$name => $value]);
                $this->refreshSettings($key);

                return true;
            }

            $result = $this->getSettings($key);

            return $result[$name] ?? null;
        }

        return null;
    }

    private function getSettings(string $key): array
    {
        if ($this->settings !== null) {
            return $this->settings;
        }

        $settings = Cache::get($key);
        if ($settings === null) {
            return $this->rememberSettings($key);
        }

        if ($settings instanceof Collection) {
            $settings = $settings->toArray();
            Cache::forever($key, $settings);
        }

        if (!is_array($settings)) {
            return $this->rememberSettings($key);
        }

        return $this->settings = $settings;
    }

    private function rememberSettings(string $key): array
    {
        try {
            return Cache::lock($key . ':lock', 10)->block(3, function () use ($key) {
                $settings = Cache::get($key);
                if ($settings instanceof Collection) {
                    $settings = $settings->toArray();
                    Cache::forever($key, $settings);
                }

                if (is_array($settings)) {
                    return $this->settings = $settings;
                }

                return $this->refreshSettings($key);
            });
        } catch (LockTimeoutException $e) {
            return $this->refreshSettings($key);
        }
    }

    private function refreshSettings(string $key): array
    {
        $this->settings = $this->freshSettings();
        Cache::forever($key, $this->settings);

        return $this->settings;
    }

    private function freshSettings(): array
    {
        return AdminSetting::query()->pluck('value', 'slug')->toArray();
    }
}
