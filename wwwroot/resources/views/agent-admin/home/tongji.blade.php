<div class="row" style="padding-top: 20px;">
    <div class="col-lg-4 col-sm-4 col-xs-12">
        <div class="main-box infographic-box">
            <i class="fa fa-money red-bg"></i>
            <span class="headline">{{ __('home.labels.balance_amount') }}</span>
            <span class="value">
<span class="timer">{{$balance_amount}}</span>
</span>
        </div>
    </div>
    <div class="col-lg-4 col-sm-4 col-xs-12">
        <div class="main-box infographic-box">
            <i class="fa fa-cart-plus emerald-bg"></i>
            <span class="headline">{{ __('home.labels.yestoday_deposit_total_amount') }}</span>
            <span class="value">
<span class="timer">{{$deposit_order_total_amount}}</span>
</span>
        </div>
    </div>
    <div class="col-lg-4 col-sm-4 col-xs-12">
        <div class="main-box infographic-box">
            <i class="fa fa-cart-plus green-bg"></i>
            <span class="headline">{{ __('home.labels.yestoday_transfer_total_amount') }}</span>
            <span class="value">
<span class="timer">{{$transfer_order_total_amount}}</span>
</span>
        </div>
    </div>
</div>
