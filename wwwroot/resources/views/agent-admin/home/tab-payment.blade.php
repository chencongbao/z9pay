<div class="search1 input-group">
    <div class="input-group-prepend">
        <span class="input-group-text" id="basic-addon1"><i class="feather icon-search"></i></span>
    </div>
    <input type="text" class="form-control searchPaymentContent"  placeholder="请输入编码名称">
</div>
<ul class="list-group list-group-flush">
    @foreach($lists as $item)
    <a class="list-group-item search-payment-item @if(\Illuminate\Support\Facades\Request::input('payment_id') == $item['id'])active @endif" href="{{\Dcat\Admin\Admin::app()->getRoute("home.statistics",['payment_id'=>$item['id']])}}#tab_payment">【#{{$item['id']}}】{{$item['name']}}</a>
    @endforeach
</ul>
<style>
    .search1 {
        padding: 10px 20px;
    }
</style>
<script>
    Dcat.ready(function () {
        $(document).off('input', '.searchPaymentContent').on('input', '.searchPaymentContent', function () {
            let keyword = $(this).val();
            if(keyword){
                $(".search-payment-item").each(function (){
                    let str = $(this).html();
                    if(str.indexOf(keyword) !== -1){
                        $(this).removeClass('hidden');
                    }else{
                        $(this).addClass('hidden');
                    }
                });
            }else{
                $(".search-payment-item").each(function (){
                    $(this).removeClass('hidden');
                });
            }
        });
    });
</script>
