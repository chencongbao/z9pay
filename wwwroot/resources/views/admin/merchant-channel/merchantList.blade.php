@include('extendtions.dcat.layout.left-filter-assets')
<div class="admin-left-filter-side">
    <button type="button" class="btn btn-sm btn-outline-primary admin-left-filter-toggle" data-expand-title="展开{{ $title ?? '侧栏' }}">
        <i class="feather icon-chevrons-left"></i>
        <span data-expand-title="展开{{ $title ?? '侧栏' }}">收起侧栏</span>
    </button>
    <div class="admin-left-filter-body">
        <div class="search1 input-group">
            <div class="input-group-prepend">
                <span class="input-group-text" id="basic-addon1"><i class="feather icon-search"></i></span>
            </div>
            <input type="text" class="form-control searchLeftContent"  placeholder="请输入商户名称">
        </div>
        <ul class="list-group list-group-flush">
            <a class="list-group-item search-merchant-item @if($merchant_user_id == 0)active @endif" href="{{bob_admin_route('merchant-channels.index')}}">全部商户</a>
            @foreach($result as $vo)
            <a class="list-group-item search-merchant-item @if($merchant_user_id == $vo['merchant_user_id'])active @endif" href="{{bob_admin_route('merchant-channels.index',['merchant_user_id'=>$vo['merchant_user_id']])}}">{{$vo['bname']}}</a>
            @endforeach
        </ul>
    </div>
</div>
<style>
    .search1 {
        padding: 10px 20px;
    }
</style>
