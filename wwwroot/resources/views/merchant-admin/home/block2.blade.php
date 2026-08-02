<div class="row">
    <div class="col-3">
        <div class="home-title">昨日充值订单</div>
        <div class="home-block text-green">
            {{$yestoday_deposit_count}}
            <em class="home-unit">单</em>
        </div>
    </div>
    <div class="col-3">
        <div class="home-title">昨日充值总额</div>
        <div class="home-block text-orange">
            {{$yestoday_deposit_amount}}
            <em class="home-unit">CNY</em>
        </div>
    </div>
    <div class="col-3">
        <div class="home-title">昨日代付/提现订单</div>
        <div class="home-block text-red">
           {{$yestoday_transfer_count}}
            <em class="home-unit">单</em>
        </div>
    </div>
    <div class="col-3">
        <div class="home-title">昨日代付/提现总额</div>
        <div class="home-block">
            {{bob_amount_format($yestoday_transfer_amount)}}
            <em class="home-unit">CNY</em>
        </div>
    </div>
</div>

<style>
    .home-title{
        color: rgba(0, 0, 0, .45);
        font-size: 12px;
        height: 22px;
        line-height: 22px;
        overflow: hidden;
        text-overflow: ellipsis;
        word-break: break-all;
        white-space: nowrap;
    }
    .home-block{
        margin-top: 8px;
        display: inline-block;
        line-height: 32px;
        height: 32px;
        font-size: 24px;
        margin-right: 32px;
    }
    .home-unit{
        color: rgba(0, 0, 0, .65);
        font-size: 16px;
        font-style: normal;
        margin-left: 4px;
    }
</style>
