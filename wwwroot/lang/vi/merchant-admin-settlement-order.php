<?php
return [
    'labels' => [
        'SettlementOrder'   => 'Lệnh quyết toán',
        'settlement-orders' => 'Danh sách lệnh quyết toán',
    ],
    'fields' => [
        'mid'      => 'ID đại lý',
        'amount'   => 'Số tiền',
        'status'   => '0 = Chờ duyệt, 1 = Đã duyệt, 2 = Từ chối',
        'name'     => 'Tên chủ tài khoản',
        'card_no'  => 'Số thẻ của chủ tài khoản',
        'mobile'   => 'Số điện thoại của chủ tài khoản',
        'remark'   => 'Ghi chú',
        'bank_id'  => 'ID ngân hàng',
    ],
    'options' => [
    ],
];
