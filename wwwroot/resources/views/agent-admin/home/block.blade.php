<div class="row">
    <div class="col-3">
        <div class="home-title">可用余额</div>
        <div class="home-block text-green">
            {{$available_balance}}
            <em class="home-unit">CNY</em>
        </div>
    </div>
    <div class="col-3">
        <div class="home-title">总余额</div>
        <div class="home-block">
            {{bob_amount_format($totol_balance)}}
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
