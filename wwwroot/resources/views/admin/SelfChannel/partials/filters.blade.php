<div class="row">
    <div class="col-12">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title">支付方式监控切换</h3>
            </div>
            <div class="box-body">
                <div style="margin-bottom:8px;color:#666;">
                    当前监控：<b>{{ $paymentName }}</b>
                    <div style="margin-top:6px;color:#999;">
                        请选择一个支付方式后，再查看该支付方式最后一次真实排卡状态。
                    </div>
                </div>

                <form method="get" action="{{ $indexUrl }}" style="margin:0;">
                    <div style="padding:12px 14px;border:1px solid #ebeef5;border-radius:6px;background:#fafbfc;">
                        <div style="font-size:13px;font-weight:600;color:#586cb1;margin-bottom:12px;">监控支付方式</div>
                        <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
                            <div style="width:240px;">
                                <div style="font-size:12px;color:#999;margin-bottom:6px;">支付方式</div>
                                <select name="payment_id" class="form-control self-channel-payment-select" data-placeholder="请选择支付方式">
                                    <option value=""></option>
                                    @foreach($paymentOptions as $id => $name)
                                        <option value="{{ $id }}" @selected($paymentId === (int) $id)>{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div style="padding-bottom:1px;">
                                <button type="submit" class="btn btn-primary btn-sm">查看排卡结果</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
