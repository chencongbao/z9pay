<?php
return [
    'labels' => [
        'users' => "账号管理",
        'User' => "账号管理",
    ],
    "fields" => [
        "merchant_coder" => "商户代码",
        "please_select_role" => "请选择角色",
        "login_white_ip_validate" => "多个IP请用逗号或换行隔开，支持单个IP或CIDR，如：1.1.1.1、1.1.1.0/24",
        "login_white_ip_invalid" => "登录IP白名单包含非法IP：:ip",
        "username_required" => "请输入子账号用户名",
        "username_unique" => "子账号用户名已存在",
        "password_required" => "请输入子账号登录密码",
        "password_min" => "子账号登录密码不能少于5位",
        "password_max" => "子账号登录密码不能超过20位",
        "password_confirm_same" => "两次输入的登录密码不一致",
        "name_required" => "请输入子账号名称",
        "role_required" => "请选择子账号角色",
        "role_invalid" => "请选择当前商户可用的子账号角色",
        "status_required" => "请选择子账号状态"
    ]
];
