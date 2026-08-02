<div class="search1 input-group">
    <div class="input-group-prepend">
        <span class="input-group-text" id="basic-addon1"><i class="feather icon-search"></i></span>
    </div>
    <input type="text" class="form-control searchLeftContent"  placeholder="请输入渠道名称">
</div>
<ul class="list-group list-group-flush">
    @foreach($result as $vo)
        <a class="list-group-item search-merchant-item @if($channel_id == $vo['id'])active @endif" href="{{bob_admin_route('report-channels.index',['channel_id'=>$vo['id']])}}">{{$vo['bname']}}</a>
    @endforeach
</ul>

<style>
    .search1 {
        padding: 10px 20px;
    }
</style>
