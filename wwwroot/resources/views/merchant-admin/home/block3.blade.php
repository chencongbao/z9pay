<div class="row" style="padding-top: 20px;">
    <div class="col-lg-6 col-sm-6 col-xs-12">
        <div class="main-box infographic-box">
            <i class="fa fa-money red-bg"></i>
            <span class="headline">{{admin_trans_label('balance_amount')}}</span>
            <span class="value">
<span class="timer">{{$balance_amount}}</span>
</span>
        </div>
    </div>
    <div class="col-lg-6 col-sm-6 col-xs-12">
        <div class="main-box infographic-box">
            <i class="fa fa-money red-bg"></i>

            <div style="display:flex;justify-content:space-between;text-align:right;">

                <!-- CNY 可用余额 -->
                <div style="flex:1;">
                    <div style="font-size:1.2em;color:#868e96;font-weight: 300">{{admin_trans_label('available_balance')}}</div>
                    <div class="timer" style="font-size:2.1em;font-weight:600;color:#343a40;padding-top: 10px;margin-top: -5px">
                        {{ $available_balance }}
                    </div>
                </div>

                @if($is_usdt_ava_rate == 1)
                <!-- USDT 平均费率 -->
                <div style="flex:1;">
                    <div style="font-size:1.2em;color:#868e96;font-weight: 300">USDT {{admin_trans_label('ava_rate')}}</div>
                    <div class="timer" style="font-size:2.1em;font-weight:600;color:#343a40;padding-top: 10px;margin-top: -5px">
                        {{ $usdt_ava_rate }}
                    </div>
                </div>

                <!-- USDT 可用余额 -->
                <div style="flex:1;">
                    <div style="font-size:1.2em;color:#868e96;font-weight: 300">USDT {{admin_trans_label('available_balance')}}</div>
                    <div class="timer" style="font-size:2.1em;font-weight:600;color:#343a40;padding-top: 10px;margin-top: -5px">
                        {{ $available_usdt_balance }}
                    </div>
                </div>

                @endif

            </div>

        </div>
    </div>
    <div class="col-lg-6 col-sm-6 col-xs-12">
        <div class="main-box infographic-box">
            <i class="fa fa-money emerald-bg"></i>
            <span class="headline">{{admin_trans_label('settlementing_amount')}}</span>
            <span class="value">
<span class="timer">{{$settlementing_amount}}</span>
</span>
        </div>
    </div>
    <div class="col-lg-6 col-sm-6 col-xs-12">
        <div class="main-box infographic-box">
            <i class="fa fa-money green-bg"></i>
            <span class="headline">{{admin_trans_label('freeze_amount')}}</span>
            <span class="value">
            <span class="timer">{{$freeze_amount}}</span>
            </span>
        </div>
    </div>
</div>

