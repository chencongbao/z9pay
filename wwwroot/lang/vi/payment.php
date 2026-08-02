<?php

return [
    'labels' => [
        'MerchantPayment' => 'Tỷ lệ thương gia',
        'merchant-payments' => 'Tỷ lệ thương gia',
        'Payment' => 'Tỷ lệ thương gia',
    ],
    'fields' => [
        'merchant_user_id' => 'Thương gia',
        'payment_id' => 'Phương thức thanh toán',
        'status' => 'Trạng thái',
        'pay_rate' => 'Tỷ lệ thanh toán',
        'agent_rate' => 'Tỷ lệ đại lý',
        'min_limit_amount' => 'Giới hạn giao dịch tối thiểu',
        'max_limit_amount' => 'Giới hạn giao dịch tối đa',
    ],
    'options' => [
        'names' => [
            'test' => 'Kiểm thử',
            'bank' => 'Thẻ ngân hàng',
            'alipay' => 'Alipay',
            'alipay_qr' => 'Mã QR Alipay',
            'wechat' => 'WeChat',
            'wechat_qr' => 'Mã QR WeChat',
            'e_rmb' => 'Nhân dân tệ số',
            'transfer' => 'Chi hộ',
        ],
    ],
];
