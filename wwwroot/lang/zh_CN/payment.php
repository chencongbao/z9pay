<?php
return [
    'labels' => [
        'MerchantPayment' => '商户费率',
        'merchant-payments' => '商户费率',
        'Payment' => '商户费率',
    ],
    'fields' => [
        'merchant_user_id' => '所属商户',
        'payment_id' => '支付方式',
        'status' => '状态',
        'pay_rate' => '支付费率',
        'agent_rate' => '代理费率',
        'min_limit_amount' => '单笔最小限额',
        'max_limit_amount' => '单笔最大限额',
    ],
    'options' => [
        'names' => [
            'test' => '测试编码',
            'bank' => '银行卡',
            'alipay' => '支付宝',
            'alipay_qr' => '支付宝扫码',
            'wechat' => '微信',
            'wechat_qr' => '微信扫码',
            'e_rmb' => '数字人民币',
            'transfer' => '代付',
        ],
    ],
];
