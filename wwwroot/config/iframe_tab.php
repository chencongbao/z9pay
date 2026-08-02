<?php
return [
    # 是否开启iframe_tab
    'enable'                => env('START_IFRAME_TAB', true),
    # 底部设置
    'footer_setting'        => [
        'copyright'         => env('APP_NAME', ''),
        'app_version'       => env('APP_VERSION', ''),
        # 是否将底部置于菜单下
        'use_menu'          => false
    ],
    # 是否开启标签页缓存
    'cache'                 => env('IFRAME_TAB_CACHE', true),
    # 更改dialog表单默认宽高
    'dialog_area_width'     => env('IFRAME_TAB_DIALOG_AREA_WIDTH', '40%'),
    'dialog_area_height'    => env('IFRAME_TAB_DIALOG_AREA_HEIGHT', '80vh'),
    # iframe-tab占用的路由 默认 '/'
    'router'                => '/',
    # iframe-tab域名（一般用于多应用后台）
    'domain'                 => null,
    # 是否开启懒加载模式
    'lazy_load'              => true,
    # 切换到 tab 时自动刷新的 URL 规则，支持 * 通配符
    'activate_refresh_patterns' => [
        '/freeze-orders*',
        '/settlement-orders*',
        '/merchant-balance-logs*',
        '/agent-balance-logs*',
        '/user-agent-balance-logs*',
        '/tusers*',
        '/user-balance-logs*',
        '/bank-users*',
    ],
];
