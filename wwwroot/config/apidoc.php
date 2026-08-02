<?php
return [
    // （选配）文档标题，显示在左上角与首页
    'title' => 'Api Document',
    // （选配）文档描述，显示在首页
    'desc' => 'Api Document',
    // （必须）设置文档的应用/版本
    'apps' => [
        [
            // （必须）标题
            'title' => "API-V3",
            // （必须）控制器目录地址
            'path' => 'app\Http\Controllers\Api\V3',
            // （必须）唯一的key
            'key' => 'api',
        ]
    ],
    'definitions' => "app\Services\Apidoc\Definitions",
    // （必须）自动生成url规则，当接口不添加@Apidoc\Url ("xxx")注解时，使用以下规则自动生成
    'auto_url' => [
        // 字母规则，lcfirst=首字母小写；ucfirst=首字母大写；
        'letter_rule' => "lcfirst",
        // url前缀
        'prefix' => "",
    ],
    // 是否自动注册路由
    'auto_register_routes' => false,
    // （必须）缓存配置
    'cache' => [
        // 是否开启缓存
        'enable' => true,
        'folder' => "apidoc"
    ],
    // （必须）权限认证配置
    'auth' => [
        // 是否启用密码验证
        'enable' => true,
        // 全局访问密码
        'password' => "api123",
        // 密码加密盐
        'secret_key' => "apidoc#hg_code",
        // 授权访问后的有效期
        'expire' => 24 * 60 * 60
    ],
    // 全局参数
    'params' => [
        // （选配）全局的请求Header
        'header' => [
            // name=字段名，type=字段类型，require=是否必须，default=默认值，desc=字段描述
            //['name' => 'Authorization', 'type' => 'string', 'require' => true, 'desc' => '身份令牌Token'],
        ],
        // （选配）全局的请求Query
        'query' => [
            // 同上 header
        ],
        // （选配）全局的请求Body
        'body' => [
            // 同上 header
        ],
    ],
    // （选配）apidoc路由前缀,默认apidoc
    'route_prefix' => '/api',
    //（选配）默认作者
    'default_author' => '',
    //（选配）默认请求类型
    'default_method' => 'POST',
    //（选配）允许跨域访问
    'allowCrossDomain' => false,
    /**
     * （选配）解析时忽略带@注解的关键词，当注解中存在带@字符并且非Apidoc注解，如 @key test，此时Apidoc页面报类似以下错误时:
     * [Semantical Error] The annotation "@key" in method xxx() was never imported. Did you maybe forget to add a "use" statement for this annotation?
     */
    'ignored_annitation' => [],

    // （选配）解析时忽略的方法
    'ignored_methods' => ["depositsCashier"],

    // （选配）数据库配置
    'database' => [],
    // （选配）Markdown文档
    'docs' => [
        ['title' => "trans(apidoc.docs.readme.title)", 'path' => 'docs/readme'],
        ['title' => "trans(apidoc.docs.rules.title)", 'path' => 'docs/rules'],
        ['title' => "trans(apidoc.docs.coder.title)", 'path' => 'docs/coder'],
        ['title' => "trans(apidoc.docs.question.title)", 'path' => 'docs/question'],
        [
            'title' => "trans(apidoc.docs.deposits.title)",
            'children' => [
                [
                    'title' => "trans(apidoc.docs.deposits.index.title)",
                    'children' => [
                        ['title' => "trans(apidoc.docs.readme.title)", 'path' => 'docs/deposits/index'],
                        ['title' => "trans(apidoc.currency.cny)", 'path' => 'docs/deposits/currencies/cny'],
                        ['title' => "trans(apidoc.currency.vnd)", 'path' => 'docs/deposits/currencies/vnd'],
                        ['title' => "trans(apidoc.currency.inr)", 'path' => 'docs/deposits/currencies/inr'],
                        ['title' => "trans(apidoc.currency.idr)", 'path' => 'docs/deposits/currencies/idr'],
                        ['title' => "trans(apidoc.currency.php)", 'path' => 'docs/deposits/currencies/php'],
                        ['title' => "trans(apidoc.currency.thb)", 'path' => 'docs/deposits/currencies/thb'],
                        ['title' => "trans(apidoc.currency.myr)", 'path' => 'docs/deposits/currencies/myr'],
                        ['title' => "trans(apidoc.currency.bdt)", 'path' => 'docs/deposits/currencies/bdt'],
                        ['title' => "trans(apidoc.currency.pkr)", 'path' => 'docs/deposits/currencies/pkr'],
                        ['title' => "trans(apidoc.currency.try)", 'path' => 'docs/deposits/currencies/try'],
                        ['title' => "trans(apidoc.currency.brl)", 'path' => 'docs/deposits/currencies/brl'],
                        ['title' => "trans(apidoc.currency.hk)", 'path' => 'docs/deposits/currencies/hk'],
                        ['title' => "trans(apidoc.currency.mxn)", 'path' => 'docs/deposits/currencies/mxn'],
                        ['title' => "trans(apidoc.currency.mmk)", 'path' => 'docs/deposits/currencies/mmk'],
                        ['title' => "trans(apidoc.currency.jpy)", 'path' => 'docs/deposits/currencies/jpy'],
                        ['title' => "trans(apidoc.currency.npr)", 'path' => 'docs/deposits/currencies/npr'],
                        ['title' => "trans(apidoc.currency.krw)", 'path' => 'docs/deposits/currencies/krw'],
                        ['title' => "trans(apidoc.currency.rub)", 'path' => 'docs/deposits/currencies/rub'],
                        ['title' => "trans(apidoc.currency.ngn)", 'path' => 'docs/deposits/currencies/ngn'],
                        ['title' => "trans(apidoc.currency.lak)", 'path' => 'docs/deposits/currencies/lak'],
                    ],
                ],
                ['title' => "trans(apidoc.docs.deposits.submit_utr.title)", 'path' => 'docs/deposits/submit-utr'],
                ['title' => "trans(apidoc.docs.deposits.query.title)", 'path' => 'docs/deposits/query'],
                ['title' => "trans(apidoc.docs.deposits.callback.title)", 'path' => 'docs/deposits/callback'],
            ],
        ],
        [
            'title' => "trans(apidoc.docs.transfers.title)",
            'children' => [
                [
                    'title' => "trans(apidoc.docs.transfers.index.title)",
                    'children' => [
                        ['title' => "trans(apidoc.docs.readme.title)", 'path' => 'docs/transfers/index'],
                        ['title' => "trans(apidoc.currency.cny)", 'path' => 'docs/transfers/currencies/cny'],
                        ['title' => "trans(apidoc.currency.vnd)", 'path' => 'docs/transfers/currencies/vnd'],
                        ['title' => "trans(apidoc.currency.inr)", 'path' => 'docs/transfers/currencies/inr'],
                        ['title' => "trans(apidoc.currency.idr)", 'path' => 'docs/transfers/currencies/idr'],
                        ['title' => "trans(apidoc.currency.php)", 'path' => 'docs/transfers/currencies/php'],
                        ['title' => "trans(apidoc.currency.thb)", 'path' => 'docs/transfers/currencies/thb'],
                        ['title' => "trans(apidoc.currency.myr)", 'path' => 'docs/transfers/currencies/myr'],
                        ['title' => "trans(apidoc.currency.bdt)", 'path' => 'docs/transfers/currencies/bdt'],
                        ['title' => "trans(apidoc.currency.pkr)", 'path' => 'docs/transfers/currencies/pkr'],
                        ['title' => "trans(apidoc.currency.try)", 'path' => 'docs/transfers/currencies/try'],
                        ['title' => "trans(apidoc.currency.brl)", 'path' => 'docs/transfers/currencies/brl'],
                        ['title' => "trans(apidoc.currency.hk)", 'path' => 'docs/transfers/currencies/hk'],
                        ['title' => "trans(apidoc.currency.mxn)", 'path' => 'docs/transfers/currencies/mxn'],
                        ['title' => "trans(apidoc.currency.mmk)", 'path' => 'docs/transfers/currencies/mmk'],
                        ['title' => "trans(apidoc.currency.jpy)", 'path' => 'docs/transfers/currencies/jpy'],
                        ['title' => "trans(apidoc.currency.npr)", 'path' => 'docs/transfers/currencies/npr'],
                        ['title' => "trans(apidoc.currency.krw)", 'path' => 'docs/transfers/currencies/krw'],
                        ['title' => "trans(apidoc.currency.rub)", 'path' => 'docs/transfers/currencies/rub'],
                        ['title' => "trans(apidoc.currency.ngn)", 'path' => 'docs/transfers/currencies/ngn'],
                        ['title' => "trans(apidoc.currency.lak)", 'path' => 'docs/transfers/currencies/lak'],
                    ],
                ],
                ['title' => "trans(apidoc.docs.transfers.query.title)", 'path' => 'docs/transfers/query'],
                ['title' => "trans(apidoc.docs.transfers.callback.title)", 'path' => 'docs/transfers/callback'],
                ['title' => "trans(apidoc.api.transfercheck.title)", 'path' => 'docs/transfers/check'],
            ],
        ],
        ['title' => "trans(apidoc.docs.balance.title)", 'path' => 'docs/balance'],
        ['title' => "trans(apidoc.docs.error_codes.title)", 'path' => 'docs/error-codes'],
    ],
    // （选配）代码生成器配置 注意：是一个二维数组
    'generator' => [],
    // （选配）代码模板
    'code_template' => [],
    // （选配）接口分享功能
    'share' => [
        // 是否开启接口分享功能
        'enable' => false,
        // 自定义接口分享操作，二维数组，每个配置为一个按钮操作
        'actions' => []
    ],
    'lang_get_function' => "trans",
    'lang_register_function' => 'bob_set_locale',
    "export_config" => [
        "enable" => true,
        "url" => "/apidocument/API-V3-offline-doc-20260723_120309.zip"
    ]
];
