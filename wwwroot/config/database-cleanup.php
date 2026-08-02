<?php

return [
    /*
    |--------------------------------------------------------------------------
    | 数据库清理开关
    |--------------------------------------------------------------------------
    |
    | 每个项目可以通过 .env 单独控制是否启用数据库定时清理。
    |
    */
    'enabled' => env('DATABASE_CLEANUP_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | 定时执行时间
    |--------------------------------------------------------------------------
    |
    | App\Console\Kernel 注册清理命令时会读取这个 Cron 表达式。
    | 默认每天凌晨 03:00 执行一次。
    |
    */
    'cron' => env('DATABASE_CLEANUP_CRON', '"*/30 * * * *'),

    /*
    |--------------------------------------------------------------------------
    | 默认数据保留时间
    |--------------------------------------------------------------------------
    |
    | 单张表没有单独设置 retention_months 时，会使用这里的默认月份。
    | 默认保留最近 3 个月的数据。
    |
    */
    'retention_months' => (int) env('DATABASE_CLEANUP_RETENTION_MONTHS', 6),

    /*
    |--------------------------------------------------------------------------
    | 分批删除控制
    |--------------------------------------------------------------------------
    |
    | 清理命令会分批删除数据，减少锁表时间，避免单次事务过大。
    | 这些值也可以通过命令参数临时覆盖。
    |
    */
    'batch' => (int) env('DATABASE_CLEANUP_BATCH', 1000),
    'max_batches' => (int) env('DATABASE_CLEANUP_MAX_BATCHES', 5),
    'sleep_ms' => (int) env('DATABASE_CLEANUP_SLEEP_MS', 300),
    'max_runtime_seconds' => (int) env('DATABASE_CLEANUP_MAX_RUNTIME_SECONDS', 120),

    /*
    |--------------------------------------------------------------------------
    | 清理表配置
    |--------------------------------------------------------------------------
    |
    | 确认需要清理的表之后，在这里添加每张表的清理规则。
    |
    | 示例：
    | 'deposit_orders' => [
    |     'enabled' => true,              // 是否启用这张表的清理
    |     'strategy' => 'primary_key_window', // 使用主键窗口扫描，适合不能新增时间索引的大表
    |     'date_column' => 'created_at',  // 用哪个时间字段判断是否过期
    |     'date_type' => 'datetime',      // datetime 表示日期时间字段，timestamp 表示 Unix 时间戳
    |     'key_column' => 'id',           // 删除时用于定位数据的主键字段
    |     'window_size' => 50000,         // 每批扫描的 ID 区间大小
    |     'retention_months' => null,     // null 表示使用全局默认保留月份
    | ],
    |
    */
    'tables' => [
        'deposite_order_logs' => [
            'enabled' => true,
            'strategy' => 'primary_key_window',
            'date_column' => 'created_at',
            'date_type' => 'datetime',
            'key_column' => 'id',
            'window_size' => 50000,
            'retention_months' => null,
        ],

        'deposit_orders' => [
            'enabled' => true,
            'strategy' => 'primary_key_window',
            'date_column' => 'created_at',
            'date_type' => 'datetime',
            'key_column' => 'id',
            'window_size' => 50000,
            'retention_months' => null,
        ],

        'merchant_balance_logs' => [
            'enabled' => true,
            'strategy' => 'primary_key_window',
            'date_column' => 'created_at',
            'date_type' => 'datetime',
            'key_column' => 'id',
            'window_size' => 50000,
            'retention_months' => null,
        ],

        'transfer_order_logs' => [
            'enabled' => true,
            'strategy' => 'primary_key_window',
            'date_column' => 'created_at',
            'date_type' => 'datetime',
            'key_column' => 'id',
            'window_size' => 50000,
            'retention_months' => null,
        ],

        'transfer_orders' => [
            'enabled' => true,
            'strategy' => 'primary_key_window',
            'date_column' => 'created_at',
            'date_type' => 'datetime',
            'key_column' => 'id',
            'window_size' => 50000,
            'retention_months' => null,
        ],

        'agent_balance_logs' => [
            'enabled' => true,
            'strategy' => 'primary_key_window',
            'date_column' => 'created_at',
            'date_type' => 'datetime',
            'key_column' => 'id',
            'window_size' => 50000,
            'retention_months' => null,
        ],

        'user_balance_logs' => [
            'enabled' => true,
            'strategy' => 'primary_key_window',
            'date_column' => 'created_at',
            'date_type' => 'datetime',
            'key_column' => 'id',
            'window_size' => 50000,
            'retention_months' => null,
        ],

        'websockets_statistics_entries' => [
            'enabled' => true,
            'strategy' => 'primary_key_window',
            'date_column' => 'created_at',
            'date_type' => 'datetime',
            'key_column' => 'id',
            'window_size' => 50000,
            'retention_months' => null,
        ],

        'activity_log' => [
            'enabled' => true,
            'strategy' => 'primary_key_window',
            'date_column' => 'created_at',
            'date_type' => 'datetime',
            'key_column' => 'id',
            'window_size' => 50000,
            'retention_months' => null,
        ]
    ],
];
