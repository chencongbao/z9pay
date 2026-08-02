<div class="search1 input-group">
    <div class="input-group-prepend">
        <span class="input-group-text" id="basic-addon1"><i class="feather icon-search"></i></span>
    </div>
    <input type="text" class="form-control searchMerchantContent"  placeholder="请输入商户名称">
</div>
<ul class="list-group list-group-flush">
    @foreach($lists as $item)
    <a class="list-group-item search-merchant-item @if(\Illuminate\Support\Facades\Request::input('mid') == $item['id'])active @endif" href="{{\Dcat\Admin\Admin::app()->getRoute("today.index",['mid'=>$item['id']])}}#tab_merchant">【#{{$item['id']}}】【{{$item['coder']}}】{{$item['name']}}</a>
    @endforeach
</ul>
<style>
    .search1 {
        padding: 10px 20px;
    }
</style>
<script>
    Dcat.ready(function () {
        $(document).off('input', '.searchMerchantContent').on('input', '.searchMerchantContent', function () {
            let keyword = $(this).val();
            if(keyword){
                $(".search-merchant-item").each(function (){
                    let str = $(this).html();
                    if(str.indexOf(keyword) !== -1){
                        $(this).removeClass('hidden');
                    }else{
                        $(this).addClass('hidden');
                    }
                });
            }else{
                $(".search-merchant-item").each(function (){
                    $(this).removeClass('hidden');
                });
            }
        });
    });
</script>
