<div class="form-check form-check-inline" style="width: 100%">
    <label class="control-label" style="padding: 0px;padding-right: 10px;"><span>1、金主代收待付款订单总金额不能超过</span> </label>
    <input class="form-control field_email _normal_" type="input" value="{{bob_admin_setting("push_pay_order_total_amount") ?: 0}}" name="push_pay_order_total_amount" style="width: 100px;text-align: center" required>
    <label class="control-label" style="padding: 0px;padding-right: 10px;padding-left: 10px"> <span>元</span></label>
</div>
<span class="help-block">
    <i class="fa feather icon-help-circle"></i>&nbsp;0表示不限制；代收待付款总金额达到或超过该值后，将不再继续排卡
</span>
