<?php

namespace App\Services\Enums;

class OkxCurrencyEnum
{
    public const CNY = 'CNY';
    public const VND = 'VND';
    public const INR = 'INR';
    public const IDR = 'IDR';
    public const PHP = 'PHP';
    public const THB = 'THB';
    public const MYR = 'MYR';
    public const BDT = 'BDT';
    public const PKR = 'PKR';
    public const TRY = 'TRY';
    public const BRL = 'BRL';
    public const HKD = 'HKD';
    public const MXN = 'MXN';
    public const MMK = 'MMK';
    public const JPY = 'JPY';
    public const NPR = 'NPR';
    public const KRW = 'KRW';
    public const RUB = 'RUB';
    public const NGN = 'NGN';
    public const LAK = 'LAK';

    /**
     * 项目内部 currency_id => OKX quoteCurrency
     */
    public const CURRENCY_ID_MAP = [
        1 => self::CNY,
        2 => self::VND,
        3 => self::INR,
        4 => self::IDR,
        5 => self::PHP,
        6 => self::THB,
        7 => self::MYR,
        8 => self::BDT,
        9 => self::PKR,
        10 => self::TRY,
        11 => self::BRL,
        12 => self::HKD,
        13 => self::MXN,
        14 => self::MMK,
        15 => self::JPY,
        16 => self::NPR,
        17 => self::KRW,
        18 => self::RUB,
        19 => self::NGN,
        20 => self::LAK,
    ];

    public const MAP = [
        self::CNY => '人民币',
        self::VND => '越南盾',
        self::INR => '印度卢比',
        self::IDR => '印尼盾',
        self::PHP => '菲律宾比索',
        self::THB => '泰铢',
        self::MYR => '马来西亚林吉特',
        self::BDT => '孟加拉国塔卡',
        self::PKR => '巴基斯坦卢比',
        self::TRY => '土耳其里拉',
        self::BRL => '巴西雷亚尔',
        self::HKD => '港币',
        self::MXN => '墨西哥比索',
        self::MMK => '缅甸元',
        self::JPY => '日元',
        self::NPR => '尼泊尔卢比',
        self::KRW => '韩元',
        self::RUB => '俄罗斯卢布',
        self::NGN => '尼日利亚奈拉',
        self::LAK => '老挝基普',
    ];

    public static function all(): array
    {
        return self::MAP;
    }

    public static function name(string $code): string
    {
        return self::MAP[$code] ?? 'Unknown';
    }

    public static function codeByCurrencyId(int $currencyId): string
    {
        return self::CURRENCY_ID_MAP[$currencyId] ?? '';
    }

    public static function hasCurrencyId(int $currencyId): bool
    {
        return isset(self::CURRENCY_ID_MAP[$currencyId]);
    }
}
