<div class="form-check form-check-inline" style="width: 100%">
    <label class="control-label" style="padding: 0px;padding-right: 10px;"><span>4、收款卡</span> </label>
    <input class="form-control field_email _normal_" type="input" value="{{bob_admin_setting("pending_pay_order_time") ?: 0}}" name="pending_pay_order_time" style="width: 100px;text-align: center" required>
    <label class="control-label" style="padding: 0px;padding-right: 10px;padding-left: 10px"> <span>分钟内，代收待付款订单不得超过</span></label>
    <input class="form-control field_email _normal_" type="input"  value="{{bob_admin_setting("pending_pay_order_number") ?: 0}}" name="pending_pay_order_number" style="width: 100px;text-align: center" required>
    <label class="control-label" style="padding: 0px;padding-right: 10px;padding-left: 10px">单</label>
</div>
<span class="help-block">
    <i class="fa feather icon-help-circle"></i>&nbsp;时间或单数任一为0都表示不开启限制；代收待付款总单数达到或超过该值后，将不再继续排卡
</span>
