<?php
return [
    'labels' => [
        'DepositOrder' => '充值订单',
        'deposit-order' => '充值订单',
        'deposit-orders' => '充值订单',
    ],
    'fields' => [
        'merchant_user_id' => '商户ID',
        'magent_user_id' => '商户代理ID',
        'user_id' => '金主ID',
        'uagent_user_id' => '金主代理ID',
        'amount' => '订单充值金额',
        'pay_amount' => '实际支付订单金额',
        'fee' => '充值订单产生的手续费',
        'currency_id' => '货币类型 CNY、USDT、 VND、INR',
        'order_no' => '商户唯一订单号',
        'ordernumber' => '订单号',
        'payment_id' => 'gateway',
        'notify_url' => '商户提供通知付款结果的接口地址',
        'return_url' => '同步跳转商户平台的地址',
        'extra' => '穿透参数，原样返回商户的参数',
        'tag' => '登录商户后台输入的商户代码',
        'ip' => '客户IP地址',
        'bank_code' => '会员付款银行代码，或者 支付宝=ALIPAY，微信 =WECHAT',
        'member_id' => '商户提供的会员ID',
        'member_level' => '商户提供的会员等级',
        'member_name' => '会员姓名',
        'member_email' => '会员邮箱',
        'member_phone' => '会员电话',
        'data_type' => '如需返回收款卡信息，商户自己封装收银台，请传json',
        'status' => '订单状态'
    ],
    'options' => [
    ],
];
