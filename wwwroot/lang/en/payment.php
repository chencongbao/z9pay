<?php

return [
    'labels' => [
        'MerchantPayment' => 'Merchant Rates',
        'merchant-payments' => 'Merchant Rates',
        'Payment' => 'Merchant Rates',
    ],
    'fields' => [
        'merchant_user_id' => 'Merchant',
        'payment_id' => 'Payment Method',
        'status' => 'Status',
        'pay_rate' => 'Payment Rate',
        'agent_rate' => 'Agent Rate',
        'min_limit_amount' => 'Minimum Transaction Limit',
        'max_limit_amount' => 'Maximum Transaction Limit',
    ],
    'options' => [
        'names' => [
            'test' => 'Test',
            'bank' => 'Bank Card',
            'alipay' => 'Alipay',
            'alipay_qr' => 'Alipay QR',
            'wechat' => 'WeChat',
            'wechat_qr' => 'WeChat QR',
            'e_rmb' => 'Digital RMB',
            'transfer' => 'Payout',
        ],
    ],
];
