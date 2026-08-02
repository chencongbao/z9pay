<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #20324a;
            font-family: Arial, Helvetica, sans-serif;
            background: #1f56c8;
        }

        .receipt-page {
            width: 430px;
            min-height: 760px;
            padding: 25px 30px 0;
            background: linear-gradient(180deg, #203f9a 0%, #1d6ff0 100%);
        }

        .ticket {
            position: relative;
            overflow: hidden;
            min-height: 682px;
            padding: 25px 23px 84px;
            background: #fff;
        }

        .success-icon {
            width: 96px;
            height: 96px;
            margin: 0 auto 12px;
            border: 7px solid #1556c7;
            border-radius: 50%;
            position: relative;
        }

        .success-icon:after {
            content: "";
            position: absolute;
            left: 22px;
            top: 24px;
            width: 45px;
            height: 22px;
            border-left: 8px solid #1556c7;
            border-bottom: 8px solid #1556c7;
            transform: rotate(-45deg);
        }

        .title {
            margin: 0;
            color: #1556c7;
            text-align: center;
            font-size: 24px;
            font-weight: 800;
        }

        .time {
            margin-top: 7px;
            color: #9ba6b6;
            text-align: center;
            font-size: 14px;
            font-weight: 700;
        }

        .amount {
            margin: 44px 0 34px;
            color: #23364f;
            text-align: center;
            font-size: 31px;
            font-weight: 800;
            letter-spacing: 1px;
        }

        .divider {
            height: 1px;
            margin: 0 0 25px;
            border-top: 1px dotted #aeb6c2;
        }

        .section {
            margin-bottom: 25px;
        }

        .section-title {
            margin-bottom: 10px;
            color: #263a59;
            font-size: 16px;
            font-weight: 800;
            letter-spacing: .3px;
        }

        .row {
            display: flex;
            align-items: flex-start;
            min-height: 24px;
            color: #9ba6b6;
            font-size: 14px;
            line-height: 1.35;
        }

        .label {
            flex: 0 0 145px;
            color: #b0b4bc;
            font-weight: 700;
        }

        .value {
            flex: 1;
            color: #8c98aa;
            text-align: right;
            font-weight: 800;
            word-break: break-word;
        }

        .value.strong {
            color: #263a59;
        }

        .description {
            color: #9ba6b6;
            font-size: 12px;
            font-weight: 700;
            line-height: 1.25;
        }

        .ticket:after {
            content: "";
            position: absolute;
            left: 0;
            right: 0;
            bottom: -8px;
            height: 16px;
            background:
                radial-gradient(circle at 8px 8px, transparent 8px, #fff 8.5px) 0 0 / 22px 16px repeat-x;
        }

        .footer {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 22px;
            height: 53px;
            color: #fff;
            font-size: 18px;
            font-weight: 800;
        }

        .footer span {
            opacity: .96;
        }
    </style>
</head>
<body>
<div class="receipt-page">
    <div class="ticket">
        <div class="success-icon"></div>
        <h1 class="title">{{ $labels['title'] }}</h1>
        <div class="time">{{ $success_time }}</div>

        <div class="amount">{{ $amount_text }}</div>
        <div class="divider"></div>

        <div class="section">
            <div class="section-title">{{ $labels['transfer_to'] }}</div>
            <div class="row">
                <div class="label">{{ $labels['account_name'] }}</div>
                <div class="value strong">{{ $holder_name }}</div>
            </div>
            <div class="row">
                <div class="label">{{ $labels['account_number'] }}</div>
                <div class="value">{{ $card_no }}</div>
            </div>
            <div class="row">
                <div class="label">{{ $labels['amount'] }}</div>
                <div class="value">{{ $amount_text }}</div>
            </div>
        </div>

        <div class="divider"></div>

        <div class="section">
            <div class="section-title">{{ $labels['transaction_details'] }}</div>
            <div class="row">
                <div class="label">{{ $labels['bank_code'] }}</div>
                <div class="value">{{ $bank_code }}</div>
            </div>
            <div class="row">
                <div class="label">{{ $labels['bank_reference'] }}</div>
                <div class="value">{{ $bank_reference }}</div>
            </div>
            <div class="row">
                <div class="label">{{ $labels['bank_result'] }}</div>
                <div class="value strong">{{ $labels['success'] }}</div>
            </div>
            <div class="row">
                <div class="label">{{ $labels['description'] }}</div>
                <div class="value description">{{ $description }}</div>
            </div>
        </div>
    </div>

    @if($show_ph_footer)
        <div class="footer">
            <span>QRPh</span>
            <span>instaPay</span>
            <span>PESONet</span>
        </div>
    @endif
</div>
</body>
</html>
