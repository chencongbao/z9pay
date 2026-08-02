@php
    $currencyItem = collect(config('default.currency'))->firstWhere('id', optional($order)->currency_id);
    $currencyName = optional($currencyItem)->offsetGet('short_name') ?: '-';
    $paymentName = optional($order)->bank_name;
    if (empty($paymentName) && optional($order)->bank_id) {
        $paymentName = optional(\App\Models\BankCode::find(optional($order)->bank_id))->name;
    }
    $paymentName = $paymentName ?: 'Bank Transfer';
    $statusText = match ((int) optional($order)->status) {
        1 => 'Created',
        2 => 'Pending Payment',
        3 => 'Pending Processing',
        4 => 'Successful',
        5 => 'Failed',
        6 => 'Processing',
        default => '-',
    };

    $successAt = optional($order)->success_time ? date('Y-m-d H:i:s', optional($order)->success_time) : (optional($order)->created_at ?: '-');
    $transNo = optional($order)->channel_ordernumber ?: (optional($order)->ordernumber ?: '-');
    $refNo = optional($order)->utr ?: (optional($order)->order_no ?: '-');
    $bankAccName = optional($order)->holder_name ?: '-';
    $bankAccNo = optional($order)->card_no ?: '-';
    $amount = optional($order)->actual_amount > 0 ? optional($order)->actual_amount : optional($order)->amount;
    $imageUrl = null;
    $amountDisplay = is_numeric($amount) ? number_format((float) $amount, 2) : $amount;
    $currencySymbol = match (optional($currencyItem)->offsetGet('short_name')) {
        'CNY' => '¥',
        'PHP' => '₱',
        'VND' => '₫',
        'INR' => '₹',
        'THB' => '฿',
        'JPY' => '¥',
        default => $currencyName . ' ',
    };
    $headerAmount = $currencySymbol . $amountDisplay;
    $labels = [
        'to' => 'to',
        'order_no' => 'Order No.',
        'status' => 'Status',
        'trans_no' => 'Trans No.',
        'ref_no' => 'Ref No.',
        'success_at' => 'Success At',
        'bank_acc_name' => 'Bank Acc Name',
        'bank_acc_no' => 'Bank Acc No.',
        'currency' => 'Currency',
        'amount' => 'Amount',
    ];
@endphp

<div style="margin:0 auto;border:1px solid #cfcfcf;border-radius:3px;overflow:hidden;background:#ffffff;box-shadow:0 1px 6px rgba(0,0,0,.08);">
    <div style="background:#20c997;padding:16px 18px;color:#ffffff;border-bottom:1px solid #1aa179;">
        <div style="font-size:19px;font-weight:500;line-height:1.15;">{{ $headerAmount }}</div>
        <div style="font-size:15px;line-height:1.2;margin-top:6px;">{{ $labels['to'] }} {{ $paymentName }}</div>
    </div>
    <table style="width:100%;border-collapse:collapse;font-size:13px;background:#f8f8f8;">
        <tr>
            <td style="padding:9px 12px;border-bottom:1px solid #dfdfdf;color:#4b5563;width:38%;">{{ $labels['order_no'] }}</td>
            <td style="padding:9px 12px;border-bottom:1px solid #dfdfdf;color:#555;text-align:right;font-weight:700;">{{ optional($order)->ordernumber ?: '-' }}</td>
        </tr>
        <tr>
            <td style="padding:9px 12px;border-bottom:1px solid #dfdfdf;color:#4b5563;">{{ $labels['status'] }}</td>
            <td style="padding:9px 12px;border-bottom:1px solid #dfdfdf;text-align:right;">
                {!! bob_show_label($statusText, (int) optional($order)->status, 3) !!}
            </td>
        </tr>
        <tr>
            <td style="padding:9px 12px;border-bottom:1px solid #dfdfdf;color:#4b5563;">{{ $labels['trans_no'] }}</td>
            <td style="padding:9px 12px;border-bottom:1px solid #dfdfdf;color:#555;text-align:right;font-weight:700;">{{ $transNo }}</td>
        </tr>
        <tr>
            <td style="padding:9px 12px;border-bottom:1px solid #dfdfdf;color:#4b5563;">{{ $labels['ref_no'] }}</td>
            <td style="padding:9px 12px;border-bottom:1px solid #dfdfdf;color:#555;text-align:right;font-weight:700;">{{ $refNo }}</td>
        </tr>
        <tr>
            <td style="padding:9px 12px;border-bottom:1px solid #dfdfdf;color:#4b5563;">{{ $labels['success_at'] }}</td>
            <td style="padding:9px 12px;border-bottom:1px solid #dfdfdf;color:#555;text-align:right;font-weight:700;">{{ $successAt }}</td>
        </tr>
        <tr>
            <td style="padding:9px 12px;border-bottom:1px solid #dfdfdf;color:#4b5563;">{{ $labels['bank_acc_name'] }}</td>
            <td style="padding:9px 12px;border-bottom:1px solid #dfdfdf;color:#555;text-align:right;font-weight:700;">{{ $bankAccName }}</td>
        </tr>
        <tr>
            <td style="padding:9px 12px;border-bottom:1px solid #dfdfdf;color:#4b5563;">{{ $labels['bank_acc_no'] }}</td>
            <td style="padding:9px 12px;border-bottom:1px solid #dfdfdf;color:#555;text-align:right;font-weight:700;">{{ $bankAccNo }}</td>
        </tr>
        <tr>
            <td style="padding:9px 12px;border-bottom:1px solid #dfdfdf;color:#4b5563;">{{ $labels['currency'] }}</td>
            <td style="padding:9px 12px;border-bottom:1px solid #dfdfdf;color:#555;text-align:right;font-weight:700;">{{ $currencyName }}</td>
        </tr>
        <tr>
            <td style="padding:9px 12px;color:#4b5563;">{{ $labels['amount'] }}</td>
            <td style="padding:9px 12px;color:#555;text-align:right;font-weight:700;">{{ is_numeric($amount) ? number_format((float) $amount, 2) : $amount }}</td>
        </tr>
    </table>
</div>
