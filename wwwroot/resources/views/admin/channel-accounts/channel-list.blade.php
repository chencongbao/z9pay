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
            <input type="text" class="form-control searchLeftContent"  placeholder="请输入渠道名称">
        </div>
        <ul class="list-group list-group-flush">
            <a class="list-group-item search-merchant-item @if(intval($channel_id) <= 0)active @endif" href="{{bob_admin_route('channel-accounts.index')}}">全部渠道</a>
            @foreach($result as $vo)
                <a class="list-group-item search-merchant-item @if($channel_id == $vo['id'])active @endif" href="{{bob_admin_route('channel-accounts.index',['channel_id'=>$vo['id']])}}">{{$vo['bname']}}</a>
            @endforeach
        </ul>
    </div>
</div>

<style>
    .search1 {
        padding: 10px 20px;
    }
</style>
