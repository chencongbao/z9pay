<?php
return [
    'labels' => [
        'ReportMerchantAgent' => 'Agent Report',
        'report-merchants'    => 'Agent Reports',
    ],
    'fields' => [
        'deposit_order_number_total'     => 'Deposit Orders',
        'deposit_order_number_success'   => 'Successful Deposit Orders',
        'deposit_order_number_fail'      => 'Failed Deposit Orders',
        'deposit_order_number_overtime'  => 'Timeout Deposit Orders',
        'deposit_order_number_swiping'   => 'Fraud/Swiping Deposit Orders',
        "deposit_order_success_rate"     => "Deposit Success Rate",
        'deposit_order_total_amount'     => 'Deposit Volume',
        'deposit_order_total_fee'        => 'Deposit Merchant Fees',

        'transfer_order_number_total'    => 'Payout Orders',
        'transfer_order_number_success'  => 'Successful Payout Orders',
        'transfer_order_number_fail'     => 'Failed Payout Orders',
        'transfer_order_total_amount'    => 'Payout Volume',
        'transfer_order_total_fee'       => 'Payout Merchant Fees',

        'settlement_order_number_total'  => 'Settlement Orders',
        'settlement_order_number_success'=> 'Successful Settlement Orders',
        'settlement_order_number_fail'   => 'Failed Settlement Orders',
        'settlement_order_total_amount'  => 'Settlement Volume',
        'settlement_order_total_fee'     => 'Settlement Merchant Fees',

        'jian_total_amount'              => 'Manual Deduction Amount',
        'add_total_amount'               => 'Manual Addition Amount',

        "transfer_order_success_rate"    => "Payout Success Rate",
        "settlement_order_success_rate"  => "Settlement Success Rate",

        "deposit_menu"                   => "Deposit",
        "transfer_menu"                  => "Payout",
        "sellement_menu"                 => "Settlement",

        "date_query" => "Query Date",
        "date_add"   => "Date",

        'deposit_commission'    => 'Pay-in Commission',
        'transfer_commission'  => 'Pay-out Commission',
        'settlement_commission'=> 'Settlement Commission',
    ]
];
