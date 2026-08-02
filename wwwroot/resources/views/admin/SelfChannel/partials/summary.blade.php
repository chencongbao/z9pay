<div class="row">
    <div class="col-12">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title">金主收款卡轮询总览</h3>
            </div>
            <div class="box-body" style="line-height:1.9;">
                <div><b>今日汇总</b></div>
                <div>当前支付方式：<span style="color:#586cb1;font-weight:600;">{{ $paymentName }}</span></div>
                <div>候选来源：<span style="color:#586cb1;font-weight:600;">{{ $sourceText }}</span></div>
                <div>收款卡总数：<span style="color:#586cb1;font-weight:600;">{{ $totalCards }}</span>　可用收款卡：<span style="color:#21b978;font-weight:600;">{{ $activeCards }}</span>　暂停收款卡：<span style="color:#ef5228;font-weight:600;">{{ $pausedCards }}</span></div>
                <div>今日成功单数：<span style="color:#586cb1;font-weight:600;">{{ $todayTotalNumber }}</span></div>
                <div>今日成功金额：<span style="color:#ef5228;font-weight:600;">{{ $todayTotalAmount }}</span></div>
                <div style="margin-top:8px;color:#999;">当前面板展示的是只读的虚拟轮询队列，不会推进真实排卡顺序。</div>
            </div>
        </div>
    </div>
</div>
