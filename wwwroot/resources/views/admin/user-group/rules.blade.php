<div class="form-check form-check-inline" style="width: 100%">
    <label class="control-label" style="padding: 0px;padding-right: 10px;"><span>1、会员提单当下时间往前</span> </label>
    <input class="form-control field_email _normal_" type="input" value="{{config("push.advance_order_time")}}" name="advance_order_time" style="width: 100px;text-align: center" required>
    <label class="control-label" style="padding: 0px;padding-right: 10px;padding-left: 10px"> <span>分钟，超时或是付款方取消的不得超</span></label>
    <input class="form-control field_email _normal_" type="input"  value="{{config("push.cannel_or_cancel_order_number")}}" name="cannel_or_cancel_order_number" style="width: 100px;text-align: center" required>
    <label class="control-label" style="padding: 0px;padding-right: 10px;padding-left: 10px">张</label>
</div>
