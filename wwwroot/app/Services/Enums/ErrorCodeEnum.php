<?php

namespace App\Services\Enums;

class ErrorCodeEnum
{
    // 通用错误 (100xx)
    public const COMMON_ERROR = 10001;

    // 提单通用 (101xx) - 代收/代付共用
    public const SUBMIT_PARAM_MISSING = 10101;
    public const SUBMIT_PARAM_INVALID = 10102;
    public const SUBMIT_SIGN_INVALID = 10103;
    public const SUBMIT_MERCHANT_INVALID = 10104;
    public const SUBMIT_ORDER_DUPLICATE = 10105;
    public const SUBMIT_AMOUNT_LIMIT = 10106;
    public const SUBMIT_CHANNEL_UNAVAILABLE = 10107;
    public const SUBMIT_CHANNEL_CONFIG_MISSING = 10108;
    public const SUBMIT_IP_FORBIDDEN = 10109;
    public const SUBMIT_BALANCE_INSUFFICIENT = 10110;
    public const SUBMIT_SYSTEM_BUSY = 10111;
    public const SUBMIT_PROVIDER_ERROR = 10112;
    public const SUBMIT_RATE_ERROR = 10113;
    public const SUBMIT_BLACKLIST_IP = 10114;
    public const SUBMIT_BLACKLIST_PAYER_NAME = 10115;
    public const SUBMIT_RISK_BRUSHING = 10116;
    public const SUBMIT_ORDER_NOT_FOUND = 10117;
    public const SUBMIT_REQUERY_FAILED = 10118;
    public const SUBMIT_REQUERY_EXCEPTION = 10119;

    public static function message($code): string
    {
        return self::MESSAGES[$code] ?? '未知错误';
    }

    public const MESSAGES = [
        self::COMMON_ERROR => '未知错误，请联系客服!',
        self::SUBMIT_PARAM_MISSING => '参数缺失',
        self::SUBMIT_PARAM_INVALID => '参数格式错误',
        self::SUBMIT_SIGN_INVALID => '签名校验失败',
        self::SUBMIT_MERCHANT_INVALID => '商户不存在或停用',
        self::SUBMIT_ORDER_DUPLICATE => '订单已存在/重复提交',
        self::SUBMIT_AMOUNT_LIMIT => '金额超限',
        self::SUBMIT_CHANNEL_UNAVAILABLE => '未匹配到可用渠道',
        self::SUBMIT_CHANNEL_CONFIG_MISSING => '通道配置缺失',
        self::SUBMIT_IP_FORBIDDEN => 'IP未授权',
        self::SUBMIT_BALANCE_INSUFFICIENT => '商户余额不足',
        self::SUBMIT_SYSTEM_BUSY => '系统繁忙',
        self::SUBMIT_PROVIDER_ERROR => '下游接口异常',
        self::SUBMIT_RATE_ERROR => '通道费率异常',
        self::SUBMIT_BLACKLIST_IP => '提交IP已触发黑名单机制',
        self::SUBMIT_BLACKLIST_PAYER_NAME => '付款人姓名限制支付',
        self::SUBMIT_RISK_BRUSHING => '订单风控',
        self::SUBMIT_ORDER_NOT_FOUND => '订单不存在',
        self::SUBMIT_REQUERY_FAILED => '反查订单未通过',
        self::SUBMIT_REQUERY_EXCEPTION => '订单反查异常，请联系开发人员或关闭反查功能',
    ];
}
