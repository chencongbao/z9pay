<?php

namespace App\Services\Enums;

class DepositChannelModeEnum
{
    /**
     * 按优先级
     */
    public const PRIORITY = 1;

    /**
     * 按随机
     */
    public const RANDOM = 2;

    /**
     * 按平均（最少使用一次）
     */
    public const AVERAGE = 3;

    /**
     * 按平均轮询
     */
    public const ROUND_ROBIN = 4;

    /**
     * 按权重
     */
    public const WEIGHT = 5;

    /**
     * 模式名称映射
     */
    public const MAP = [
        self::PRIORITY => '按优先级',
        self::RANDOM => '按随机',
        self::AVERAGE => '按平均',
        self::ROUND_ROBIN => '按平均轮询',
        self::WEIGHT => '按权重',
    ];
}
