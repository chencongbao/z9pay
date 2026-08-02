<?php
return [
    // 后台系统名称：用于后台标题、谷歌验证二维码发行方等展示场景，可通过 ADMIN_NAME 覆盖。
    'name' => env('ADMIN_NAME', "支付"),
    // 系统支持币种列表：用于订单币种、报表币种、语言映射、批量代付能力等基础展示与校验。
    "currency" => [
        [
            'id' => 1,
            "status" => 1,
            "name" => "CNY-人民币",
            'short_name' => 'CNY',
            'amount_unit' => '¥',
            'rate' => 0,
            'country' => "中国",
            'lang' => "zh_CN",
            'batch_transfer' => 0
        ],
        [
            'id' => 2,
            "status" => 1,
            "name" => "VND-越南币",
            'short_name' => 'VND',
            'amount_unit' => '₫',
            'rate' => 0,
            'country' => "越南",
            'lang' => "vi",
            'batch_transfer' => 0
        ],
        [
            'id' => 3,
            "status" => 1,
            "name" => "INR-印度卢比",
            'short_name' => 'INR',
            'amount_unit' => '₹',
            'rate' => 0,
            'country' => "印度",
            'lang' => "en",
            'batch_transfer' => 1
        ],
        [
            'id' => 4,
            "status" => 1,
            "name" => "IDR-印尼盾",
            'short_name' => 'IDR',
            'amount_unit' => 'Rp',
            'rate' => 0,
            'country' => "印尼",
            'lang' => "id",
            'batch_transfer' => 0
        ],
        [
            'id' => 5,
            "status" => 1,
            "name" => "PHP-比索",
            'short_name' => 'PHP',
            'amount_unit' => '₱',
            'rate' => 0,
            'country' => "菲律宾",
            'lang' => "en",
            'batch_transfer' => 0
        ],
        [
            'id' => 6,
            "status" => 1,
            "name" => "THB-泰铢",
            'short_name' => 'THB',
            'amount_unit' => '฿',
            'rate' => 0,
            'country' => "泰国",
            'lang' => "th",
            'batch_transfer' => 0
        ],
        [
            'id' => 7,
            "status" => 1,
            "name" => "MYR-马来西亚林吉特",
            'short_name' => 'MYR',
            'amount_unit' => 'RM',
            'rate' => 0,
            'country' => "马来西亚",
            'lang' => "ms",
            'batch_transfer' => 0
        ],
        [
            'id' => 8,
            "status" => 1,
            "name" => "BDT-孟加拉国塔卡",
            'short_name' => 'BDT',
            'amount_unit' => '৳',
            'rate' => 0,
            'country' => "孟加拉国",
            'lang' => "bn",
            'batch_transfer' => 0
        ],
        [
            'id' => 9,
            "status" => 1,
            "name" => "PKR-巴基斯坦卢比",
            'short_name' => 'PKR',
            'amount_unit' => '₨',
            'rate' => 0,
            'country' => "巴基斯担",
            'lang' => "ur",
            'batch_transfer' => 0
        ],
        [
            'id' => 10,
            "status" => 1,
            "name" => "TRY-土耳其里拉",
            'short_name' => 'TRY',
            'amount_unit' => '₺',
            'rate' => 0,
            'country' => "土耳其",
            'lang' => "tr",
            'batch_transfer' => 0
        ],
        [
            'id' => 11,
            "status" => 1,
            "name" => "BRL-巴西雷亚尔",
            'short_name' => 'BRL',
            'amount_unit' => 'R$',
            'rate' => 0,
            'country' => "巴西",
            'lang' => "pt-BR",
            'batch_transfer' => 0
        ],
        [
            'id' => 12,
            "status" => 1,
            "name" => "HK-港元",
            'short_name' => 'HK',
            'amount_unit' => 'HK$',
            'rate' => 0,
            'country' => "香港",
            'lang' => "zh_CN",
            'batch_transfer' => 0
        ],
        [
            'id' => 13,
            "status" => 1,
            "name" => "MXN-墨西哥比索",
            'short_name' => 'MXN',
            'amount_unit' => 'Mex$',
            'rate' => 0,
            'country' => "墨西哥",
            'lang' => "es",
            'batch_transfer' => 0
        ],
        [
            'id' => 14,
            "status" => 1,
            "name" => "MMK-缅币",
            'short_name' => 'MMK',
            'amount_unit' => 'K',
            'rate' => 0,
            'country' => "缅甸",
            'lang' => "my",
            'batch_transfer' => 0
        ],
        [
            'id' => 15,
            "status" => 1,
            "name" => "JPY-日元",
            'short_name' => 'JPY',
            'amount_unit' => '¥',
            'rate' => 0,
            'country' => "日本",
            'lang' => "ja",
            'batch_transfer' => 0
        ],
        [
            'id' => 16,
            "status" => 1,
            "name" => "NPR-尼泊尔卢比",
            'short_name' => 'NPR',
            'amount_unit' => 'रू',
            'rate' => 0,
            'country' => "尼泊尔",
            'lang' => "ne",
            'batch_transfer' => 0
        ],
        [
            'id' => 17,
            "status" => 1,
            "name" => "KRW-韩元",
            'short_name' => 'KRW',
            'amount_unit' => '₩',
            'rate' => 0,
            'country' => "韩国",
            'lang' => "ko",
            'batch_transfer' => 0
        ],
        [
            'id' => 18,
            "status" => 1,
            "name" => "RUB-俄罗斯卢布",
            'short_name' => 'RUB',
            'amount_unit' => '₽',
            'rate' => 0,
            'country' => "俄罗斯",
            'lang' => "ru",
            'batch_transfer' => 0
        ],
        [
            'id' => 19,
            "status" => 1,
            "name" => "NGN-尼日利亚奈拉",
            'short_name' => 'NGN',
            'amount_unit' => '₦',
            'rate' => 0,
            'country' => "尼日利亚",
            'lang' => "en",
            'batch_transfer' => 0
        ],
        [
            'id' => 20,
            "status" => 1,
            "name" => "LAK-老挝",
            'short_name' => 'LAK',
            'amount_unit' => '₭',
            'rate' => 0,
            'country' => "老挝",
            'lang' => "en",
            'batch_transfer' => 0
        ]
    ],
    // 金主收款账号类型：用于金主银行卡/二维码账号列表、收银台展示和通道匹配。
    'user_bank_type' => [
        1 => "银行卡",
        2 => '支付宝',
        3 => '支付宝扫码',
        4 => '微信',
        5 => '微信扫码',
        6 => '数字人民币',
        28 => "聚合码",
        14 => "抖音"
    ],
    // 代付方式映射：用于渠道支持的代付方式配置，以及代付通道筛选和日志展示。
    "transfer_payment" => [
        "0" => "银行卡",
        "93" => "支付宝",
        "84" => "微信",
        "175" => "数字人民币"
    ],
    // 通用启用状态文案：用于后台开关、用户、商户、渠道等启用/禁用字段展示。
    "status_text" => [
        0 => "禁用",
        1 => '启用'
    ],
    // 谷歌验证状态文案：用于后台账号、商户端账号、代理端账号的谷歌验证状态显示。
    "google" => [
        'status_text' => [
            0 => "禁用",
            1 => '启用'
        ],
        'bind_text' => [
            0 => "未绑定",
            1 => '已绑定'
        ]
    ],
    // 代收订单状态文案：用于代收订单列表、导出、Telegram 查询和接口资源返回。
    'deposite_status' => [
        1 => '创建',
        2 => '刷单',
        3 => '待支付',
        4 => '超时',
        5 => '成功',
        6 => '失败',
        7 => "待确认"
    ],
    // 代收付款状态文案：用于收银台付款确认、付款取消等状态展示。
    'deposite_pay_status' => [
        1 => '待支付',
        2 => '付方已确认',
        3 => '付方已取消'
    ],
    // 代付支付状态文案：用于金主付款状态、待确认状态展示。
    'transfer_pay_status' => [
        1 => '金主待支付',
        2 => '金主已付款',
        3 => '金主已付款，待确认'
    ],
    // 代付/结算订单状态文案：用于代付订单、结算订单列表、导出和查询结果展示。
    'transfer_status' => [
        1 => "创建",
        2 => "待支付",
        3 => "待处理",
        4 => "成功",
        5 => "失败",
        6 => "处理中",
        7 => "待确认"
    ],
    // 冻结订单状态文案：用于代收冻结/解冻记录列表和导出。
    'freeze_status' => [
        0 => "解冻",
        1 => '冻结'
    ],
    // 商户余额流水类型：用于商户余额日志、后台流水筛选、导出和冲正备注展示。
    "merchant_balance_type" => [
        1 => "代收入账",
        2 => "代付扣款",
        3 => "代付失败",
        4 => "代付成功",
        5 => "代付冲正",
        6 => "结算扣款",
        7 => '结算失败',
        8 => "结算成功",
        9 => "代收冻结",
        10 => "代收解冻",
        11 => "增项金额",
        12 => "减项金额",
        13 => "结算手续费",
        14 => "流水冲正",
        15 => "结算冲正"
    ],
    // 金主余额流水类型：用于金主余额日志、后台流水筛选、导出和冲正备注展示。
    "user_balance_type" => [
        1 => "代收佣金",
        2 => "佣金账户减项",
        3 => "佣金账户加项",
        4 => "代收成功",
        5 => "代收账户减项",
        6 => "代收账户加项",
        7 => "代付成功",
        8 => "代付账户减项",
        9 => "代付账户加项",
        10 => "佣金结算",
        11 => "代付冲正",
        12 => "流水冲正",
        13 => "代付佣金",
        14 => "结算佣金",
        15 => "结算冲正",
        16 => "结算成功"
    ],
    // 金主收款账号余额流水类型：用于收款卡余额日志展示。
    'user_bank_balance' => [
        1 => "代收",
        2 => "代收加项",
        3 => "代收减项"
    ],
    // 代理余额流水类型：用于商户代理、金主代理流水列表、导出和冲正备注展示。
    "agent_balance_type" => [
        1 => "代收佣金",
        2 => "代付佣金",
        3 => "人工减项",
        4 => "人工加项",
        5 => "代付冲正",
        6 => "流水冲正",
        7 => "结算佣金",
        8 => "结算冲正"
    ],
    // 商户端结算订单状态文案：用于商户端把后台细分状态合并成更容易理解的状态。
    "merchant_settlement_order_status" => [
        2 => "已提交",
        3 => "已提交",
        4 => "成功",
        5 => "失败",
        6 => "已提交",
    ],
    // 总后台结算订单状态文案：用于后台保留更细的结算处理状态。
    "admin_merchant_settlement_order_status" => [
        2 => "待支付",
        3 => "待处理",
        4 => "成功",
        5 => "失败",
        6 => "处理中",
    ],
    // Telegram webhook 绑定域名：优先读取 TELEGRAM_WEBHOOK_DOMAIN，未配置时使用 API_ROUTE_DOMAIN。
    "telegram_webhook_domain" => env('TELEGRAM_WEBHOOK_DOMAIN') ?: env('API_ROUTE_DOMAIN'),
    // API 主域名：用于测试命令、后台代付测试、支付包回调地址等需要拼 API 域名的场景。
    "api_domain" => env('API_ROUTE_DOMAIN'),
    // 收银台主域名：用于生成代收收银台链接、二维码兼容下载等收银台入口。
    "cashier_domain" => env('CASHIER_ROUTE_DOMAIN'),
    // API 额外允许域名：用于多 API 域名入口校验和域名服务统一判断，多个用英文逗号分隔。
    "api_extra_domains" => env('API_EXTRA_DOMAINS'),
    // 黑名单类型文案：用于黑名单后台列表、筛选和提示展示。
    'black_content' => [
        'type_text' => [
            1 => "代收IP黑名单",
            2 => "代收付款人黑名单",
            3 => "收银台地区黑名单"
        ]
    ],
    // 语音/机器人提示文案：用于 Telegram 机器人设置完成等系统内置通知内容。
    'voice' => [
        'telegram' => [
            'setting_success' => [
                'error_text' => '机器人设置完成',
                'success_text' => '机器人设置完成',
                'voice_id' => "telegram_setting_success_voice"
            ]
        ]
    ],
    // 系统 Telegram 固定管理员 ID：只有开启 system_telegram_manager_on 后才作为兜底管理员生效。
    'system_telegram_id' => env('SYSTEM_TELEGRAM_ID', '7387269453'),
    // 系统 Telegram 固定管理员开关：默认关闭；关闭时只认后台配置的飞机命令管理员。
    'system_telegram_manager_on' => env('SYSTEM_TELEGRAM_MANAGER_ON', 0),
    // TRON 节点 API 地址：用于链上余额、交易、合约相关查询。
    'base_uri' => env('TRON_BASE_URI', 'https://api.trongrid.io'),
    // TRON 浏览器 API 地址：用于 Tronscan 交易列表、地址交易记录等查询。
    'explorer_uri' => env('TRON_EXPLORER_URI', 'https://apilist.tronscan.org'),
    // TRON 节点 API Key：用于访问 TronGrid，避免公共限流影响链上查询。
    'api_key' => env('TRON_API_KEY', 'b16da063-7da7-49dd-9b67-12d5597fa5fd'),
    // TRON 浏览器 API Key：用于访问 Tronscan 接口，避免查询限流。
    'explorer_api_key' => env('EXPLORER_API_KEY', 'de1db5f7-30ef-4c7f-afff-e7a4b651b61b'),
    // USDT TRC20 合约地址：用于监听、识别和查询 USDT 转账。
    "contract_address" => env('TRON_CONTRACT_ADDRESS', 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t'),
    // Telegram 查询 TRON 地址开关：开启后群内发送 TRON 地址可触发地址查询。
    "query_tron_address_on" => env("QUERY_TRON_ADDRESS_ON", 0),
    // 代收结算模式文案：用于商户渠道结算模式显示，0=T0、1=T1、2=T2。
    "settlement_mode" => [
        0 => "T0",
        1 => "T1",
        2 => "T2"
    ],
    // 金主收款账号操作类型：用于收款账号操作日志展示。
    "user_bank_action_type" => [
        1 => "添加",
        2 => "修改",
        3 => "删除",
        4 => "还原数据",
        5 => "收款开启",
        6 => "收款关闭",
        7 => "批量删除",
        8 => "确认付款"
    ],
    // 全端登录谷歌验证总开关：开启后总后台、商户端、代理端登录可跳过 2FA，仅建议本地调试使用。
    'disable_2fa' => env('DISABLE_2FA', false),
    // 成功率统计延迟分钟数：统计最近 N 分钟成功率时向前偏移，避免刚成功订单还没完全落库影响统计。
    'success_rate_query_delay_minutes' => env('SUCCESS_RATE_QUERY_DELAY_MINUTES', 0),
    // WAF 模式开关：开启后 bob_ip 会优先按 WAF/CDN 相关规则识别客户端 IP。
    "is_waf_on" => env('IS_WAF_ON', false),
    // Cloudflare 域名白名单：访问这些域名且来源 IP 是 Cloudflare 时，才读取 CF-Connecting-IP。
    'client_ip_cf_domains' => env('CLIENT_IP_CF_DOMAINS', ''),
    // 收银台预览开关：开启后可强制使用指定支付方式和币种，方便本地或测试环境预览收银台页面。
    'cashier_preview_on' => env('CASHIER_PREVIEW_ON', false),
    // 收银台预览支付方式：cashier_preview_on 开启后覆盖当前请求支付方式。
    'cashier_preview_payment' => env('CASHIER_PREVIEW_PAYMENT', 'card_pc'),
    // 收银台预览币种：cashier_preview_on 开启后覆盖当前请求币种。
    'cashier_preview_currency' => env('CASHIER_PREVIEW_CURRENCY', 'VND'),
    // 总后台谷歌验证开关：开启后仅总后台可跳过谷歌验证，和 disable_2fa 的全端开关不同。
    'admin_google_2fa_disabled' => env('ADMIN_GOOGLE_2FA_DISABLED', false),
    // 后台页面刷新按钮显示开关：控制 iframe-tab 场景下页面底部刷新按钮是否显示。
    'admin_page_refresh_button_on' => env('ADMIN_PAGE_REFRESH_BUTTON_ON', true),
    // 商户配置页是否显示菲律宾 GCash 渠道名称配置。
    'gcash_merchant_name_config_visible' => env('GCASH_MERCHANT_NAME_CONFIG_VISIBLE', false),
];
