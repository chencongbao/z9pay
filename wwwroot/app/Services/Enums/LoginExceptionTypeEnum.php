<?php

namespace App\Services\Enums;

class LoginExceptionTypeEnum
{
    const ALL = 'all';
    const SYSTEM = 'system';
    const MERCHANT = 'merchant';
    const AGENT = 'agent';
    const USER = 'user';
    const USER_AGENT = 'user_agent';

    public static function label(string $type): string
    {
        $map = self::map();

        return $map[$type]['label'] ?? $type;
    }

    public static function blacklistType(string $type): string
    {
        $map = self::map();

        return $map[$type]['blacklist_type'] ?? self::ALL;
    }

    public static function map(): array
    {
        return [
            self::ALL => [
                'label' => '全部端',
                'blacklist_type' => self::ALL,
            ],
            self::SYSTEM => [
                'label' => '超管后台',
                'blacklist_type' => 'system',
            ],
            self::MERCHANT => [
                'label' => '商户后台',
                'blacklist_type' => 'merchant',
            ],
            self::AGENT => [
                'label' => '商户代理后台',
                'blacklist_type' => 'agent',
            ],
            self::USER => [
                'label' => '金主端',
                'blacklist_type' => 'user',
            ],
            self::USER_AGENT => [
                'label' => '金主代理端',
                'blacklist_type' => 'user_agent',
            ],
        ];
    }
}
