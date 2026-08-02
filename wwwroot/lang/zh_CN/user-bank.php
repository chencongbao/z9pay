<?php
return [
    'labels' => [
        'UserBank' => '金主收款卡',
        'user-bank' => '金主收款卡',
        'bank-users' => '金主收款卡',
    ],
    'fields' => [
        'bank_id' => 'bank_id',
        'card_no' => '银行卡号,支付宝账户',
        'name' => '银行账户名，支付宝收款姓名',
        'bank_branch' => '支行',
        'payment_qrcode' => '收款码',
        'user_id' => '所属金主',
        'balance_amount' => '参考余额',
        'limint_day_amount' => '全天限额',
        'limint_min_amount' => '单笔最小限额',
        'limint_max_amount' => '单笔最大限额',
        'status' => '状态',
    ],
    'options' => [
    ],
];
