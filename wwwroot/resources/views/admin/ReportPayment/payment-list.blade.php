<div class="search1 input-group">
    <div class="input-group-prepend">
        <span class="input-group-text" id="basic-addon1"><i class="feather icon-search"></i></span>
    </div>
    <input type="text" class="form-control searchLeftContent"  placeholder="{{admin_trans_label("search_payment_tip")}}">
</div>
<ul class="list-group list-group-flush">
    @foreach($result as $vo)
        <a class="list-group-item search-merchant-item @if($payment_id == $vo['id'])active @endif" href="{{bob_admin_route('report-payments.index',['payment_id'=>$vo['id']])}}">{{$vo['bname']}}</a>
    @endforeach
</ul>

<style>
    .search1 {
        padding: 10px 20px;
    }
</style>
