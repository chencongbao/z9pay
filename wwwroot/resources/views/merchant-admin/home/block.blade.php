<div class="row" style="padding-top: 20px;">
    <div class="col-lg-6 col-sm-6 col-xs-12">
        <div class="main-box infographic-box">
            <i class="feather icon-bar-chart-2 red-bg"></i>
            <span class="headline">{{admin_trans_label('order_number_total')}}</span>
            <span class="value">
<span class="timer">{{$order_number_total}}</span>
</span>
        </div>
    </div>
    <div class="col-lg-6 col-sm-6 col-xs-12">
        <div class="main-box infographic-box">
            <i class="fa fa-money bg-pink"></i>
            <span class="headline">{{admin_trans_label('order_total_amount')}}</span>
            <span class="value">
<span class="timer">{{$order_total_amount}}</span>
</span>
        </div>
    </div>
    <div class="col-lg-6 col-sm-6 col-xs-12">
        <div class="main-box infographic-box">
            <i class="fa fa-money bg-teal"></i>
            <span class="headline">{{admin_trans_label('order_total_fee')}}</span>
            <span class="value">
<span class="timer">{{$order_total_fee}}</span>
</span>
        </div>
    </div>
    <div class="col-lg-6 col-sm-6 col-xs-12">
        <div class="main-box infographic-box">
            <i class="feather icon-percent green-bg"></i>
            <span class="headline">{{admin_trans_label('order_success_rate')}}</span>
            <span class="value">
<span class="timer">{{$order_success_rate}}</span>
</span>
        </div>
    </div>
</div>
<style>
    .main-box{
        border:2px solid #eeeeee;
    }
</style>

