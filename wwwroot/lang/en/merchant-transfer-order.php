<?php
return [
    'labels' => [
        'TransferOrder'   => 'Withdrawal Orders',
        'transfer-order'  => 'Withdrawal Orders',
        'transfer-orders' => 'Withdrawal Orders',
    ],
    'fields' => [
        'mid'                    => 'Merchant ID',
        'amount'                 => 'Payout Amount',
        'time'                   => 'Submission Timestamp',
        'currency_id'            => 'Currency Type (CNY, USDT, VND, INR)',
        'order_no'               => 'Merchant Unique Order Number',
        'ip'                     => 'Customer IP Address',
        'true_ip'                => 'Real Customer IP Address',
        'ordernumber'            => 'Order Number',
        'uid'                    => 'Member ID Provided by Merchant',
        'level'                  => 'Member Level Provided by Merchant',
        'notify_url'             => 'Merchant Payment Result Notification URL',
        'withdraw_query_url'     => 'Merchant Query API URL',
        'call_token'             => 'Merchant Query API Token',
        'callback_count'         => 'Callback Attempts',
        'callback_time'          => 'Callback Time',
        'success_time'           => 'Success Time',
        'remark'                 => 'Remarks',
        'extra'                  => 'Pass-through Parameters (Returned as-is)',
        'bank_code'              => 'Bank Code',
        'bank_name'              => 'Bank Name',
        'card_no'                => 'Bank Card Number',
        'holder_name'            => 'Account Holder Name / Alipay/WeChat Real Name',
        'bank_province'          => 'Account Opening Province',
        'bank_city'              => 'Account Opening City',
        'bank_branch'            => 'Bank Branch Address',
        'status'                 => 'Order Status',

        'merchant_rate'          => 'Merchant Fee Rate',
        'merchant_fee'           => 'Merchant Fee',

        'agent1_rate'            => 'Merchant Level 1 Agent Rate',
        'agent1_commission'      => 'Merchant Level 1 Agent Commission',
        'agent2_rate'            => 'Merchant Level 2 Agent Rate',
        'agent2_commission'      => 'Merchant Level 2 Agent Commission',

        'user_rate'              => 'Payer Fee Rate',
        'user_commission'        => 'Payer Commission',

        'user_agent1_rate'       => 'Payer Level 1 Agent Rate',
        'user_agent1_commission' => 'Payer Level 1 Agent Commission',
        'user_agent2_rate'       => 'Payer Level 2 Agent Rate',
        'user_agent2_commission' => 'Payer Level 2 Agent Commission',

        'user_agent1_id'         => 'Level 1 Agent ID',
        'user_agent2_id'         => 'Level 2 Agent ID',
        'user_id'                => 'Payer ID',

        'channel_id'             => 'Channel ID',
        'channel_account_id'     => 'Channel Account ID',
        'channel_ordernumber'    => 'Channel Order Number',
    ],
    'options' => [
    ],
];
