<div class="search1 input-group">
    <div class="input-group-prepend">
        <span class="input-group-text" id="basic-addon1"><i class="feather icon-search"></i></span>
    </div>
    <input type="text" class="form-control searchLeftContent"  placeholder="请输入商户名称">
</div>
<ul class="list-group list-group-flush">
    <a class="list-group-item search-merchant-item @if($mid == 0)active @endif" href="{{bob_admin_route('merchant-balance-logs.index')}}">全部商户</a>
    @foreach($result as $vo)
    <a class="list-group-item search-merchant-item @if($mid == $vo['merchant_user_id'])active @endif" href="{{bob_admin_route('merchant-balance-logs.index',['mid'=>$vo['merchant_user_id']])}}">{{$vo['bname']}}</a>
    @endforeach
</ul>

<style>
    .search1 {
        padding: 10px 20px;
    }
</style>
