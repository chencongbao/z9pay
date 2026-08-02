<?php

return [
    'failed' => '账号或密码错误',
    'throttle' => '登录尝试次数过多，请 :seconds 秒后再试。',

    'merchant_login' => [
        'too_many_attempts' => '登录失败次数过多，请稍后再试',
        'captcha_failed' => '滑动验证失败，请重新验证',
        'input_merchant_code' => '请输入商户编码',
        'input_username' => '请输入账号',
        'input_password' => '请输入密码',
        'input_google_code' => '请输入谷歌验证码',
        'auth_failed' => '账号或密码错误',
        'merchant_code_error' => '商户代码错误',
        'login_risk_need_white_ip' => '当前账号存在异常登录风险，请使用白名单IP登录或联系管理员配置登录白名单',
        'need_white_ip_before_google_bind' => '请先联系管理员设置登录IP白名单后，再进行谷歌绑定',
        'google_code_error' => '您输入的验证码不正确',
        'ip_not_in_white_list' => '登陆IP地址不在白名单',
        'system_error_retry' => '系统异常，请稍后重试',
    ],

    'agent_login' => [
        'too_many_attempts' => '登录失败次数过多，请稍后再试',
        'captcha_failed' => '滑动验证失败，请重新验证',
        'invalid_captcha_type' => '验证码类型不正确',
        'input_username' => '请输入账号',
        'input_password' => '请输入密码',
        'input_google_code' => '请输入谷歌验证码',
        'input_six_digit_google_code' => '请输入6位谷歌验证码',
        'auth_failed' => '账号或密码错误',
        'login_failed' => '登录失败',
        'login_risk_need_white_ip' => '当前账号存在异常登录风险，请使用白名单IP登录或联系管理员配置登录白名单',
        'need_white_ip_before_google_bind' => '请先联系管理员设置登录IP白名单后，再进行谷歌绑定',
        'google_code_error' => '您输入的验证码不正确',
        'google_verify' => '谷歌验证',
        'confirm_google_verify' => '确认验证',
        'google_verify_failed' => '谷歌验证失败',
        'verify_session_expired' => '验证会话已失效，请重新登录',
        'ip_not_in_white_list' => '登陆IP地址不在白名单',
        'system_error_retry' => '系统异常，请稍后重试',
    ],
];
