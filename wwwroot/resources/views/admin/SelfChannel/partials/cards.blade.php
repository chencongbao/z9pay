<div class="row">
    <div class="col-md-3">
        <div class="small-box" style="background:#eef3ff;border:1px solid #cddcff;color:#3556a8;border-radius:8px;min-height:120px;margin-bottom:16px;">
            <div class="inner" style="padding:18px 18px 16px;">
                <div style="font-size:13px;font-weight:700;letter-spacing:.5px;margin-bottom:12px;opacity:.9;">虚拟轮询队列</div>
                <div style="font-size:15px;font-weight:700;line-height:1.6;">当前监控：{{ $paymentName }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="small-box" style="background:#edf4ff;border:1px solid #c7dafc;color:#2457a6;border-radius:8px;min-height:120px;margin-bottom:16px;">
            <div class="inner" style="padding:18px 18px 16px;">
                <div style="font-size:13px;font-weight:700;letter-spacing:.5px;margin-bottom:10px;opacity:.9;">当前命中卡</div>
                <div style="font-size:15px;font-weight:700;line-height:1.55;word-break:break-all;">{!! $currentCardHtml !!}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="small-box" style="background:#ecfbf4;border:1px solid #bfe9d1;color:#11835b;border-radius:8px;min-height:120px;margin-bottom:16px;">
            <div class="inner" style="padding:18px 18px 16px;">
                <div style="font-size:13px;font-weight:700;letter-spacing:.5px;margin-bottom:10px;opacity:.9;">下一张卡</div>
                <div style="font-size:15px;font-weight:700;line-height:1.55;word-break:break-all;">{!! $nextCardHtml !!}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="small-box" style="background:#fff6e8;border:1px solid #f0d4a6;color:#9a6500;border-radius:8px;min-height:120px;margin-bottom:16px;">
            <div class="inner" style="padding:18px 18px 16px;">
                <div style="font-size:13px;font-weight:700;letter-spacing:.5px;margin-bottom:10px;opacity:.9;">队列概览</div>
                <div style="font-size:28px;font-weight:800;line-height:1;margin-bottom:10px;">{{ $queueLength }}</div>
                <div style="font-size:14px;font-weight:600;line-height:1.6;">虚拟节点数 / 实际卡数：{{ $realCardCount }}</div>
            </div>
        </div>
    </div>
</div>
