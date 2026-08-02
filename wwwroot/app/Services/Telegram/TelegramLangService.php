<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Lang;

class TelegramLangService
{
    public function merchantLang(int $mid = 0): string
    {
        if ($mid <= 0) {
            return 'en-US';
        }

        $lang = (string) app(GetMerchantTelegramTranslateLangService::class)->excute($mid);
        return $lang !== '' ? $lang : 'en-US';
    }

    public function locale(string $lang = ''): string
    {
        $lang = trim($lang);
        if ($lang === '') {
            return 'en';
        }

        $normalized = str_replace('_', '-', strtolower($lang));

        return match (true) {
            str_starts_with($normalized, 'zh') => 'zh_CN',
            str_starts_with($normalized, 'vi') => 'vi',
            str_starts_with($normalized, 'th') => 'th',
            str_starts_with($normalized, 'id') => 'id',
            str_starts_with($normalized, 'ms') => 'ms',
            str_starts_with($normalized, 'ja') => 'ja',
            str_starts_with($normalized, 'ko') => 'ko',
            str_starts_with($normalized, 'ru') => 'ru',
            str_starts_with($normalized, 'pt-br') => 'pt-BR',
            str_starts_with($normalized, 'bn') => 'bn',
            str_starts_with($normalized, 'ur') => 'ur',
            str_starts_with($normalized, 'ne') => 'ne',
            str_starts_with($normalized, 'my') => 'my',
            str_starts_with($normalized, 'fil') => 'fil',
            str_starts_with($normalized, 'hi') => 'hi',
            str_starts_with($normalized, 'es') => 'es',
            str_starts_with($normalized, 'tr') => 'tr',
            default => 'en',
        };
    }

    public function text(string $key, string $lang = '', array $replace = []): string
    {
        return $this->get("telegram.{$key}", $lang, $replace);
    }

    public function option(string $group, int|string $value, string $lang = ''): string
    {
        return $this->get("telegram.options.{$group}.{$value}", $lang);
    }

    private function get(string $path, string $lang = '', array $replace = []): string
    {
        $locale = $this->locale($lang);
        $translated = Lang::get($path, $replace, $locale);
        if ($translated !== $path) {
            return (string) $translated;
        }

        $fallback = Lang::get($path, $replace, 'en');
        if ($fallback !== $path) {
            return (string) $fallback;
        }

        $zh = Lang::get($path, $replace, 'zh_CN');
        if ($zh !== $path) {
            return (string) $zh;
        }

        return $path;
    }
}
