
notice_success = function(message = ''){
    $(".notice-list").append('<div class="alert alert-success alert-dismissable"><button type="button" class="close closeNotice">×</button><h4> 成功提示</h4>'+message+'</div>');
    notice_count();
}
notice_error = function(message = ''){
    $(".notice-list").append('<div class="alert alert-danger alert-dismissable"><button type="button" class="close closeNotice">×</button><h4> 错误提示</h4>'+message+'</div>');
    notice_count();
}

notice_count = function (){
    let count = parseInt($(".notice-list").find('.alert').length);
    $(".notice-count").html(count);
    if(count == 0 ){
        $(".jumbotron").css('display','block');
        $(".clear-notice").css("display",'none');
    }else{
        $(".jumbotron").css('display','none');
        $(".clear-notice").css("display",'block');
    }
}

Dcat.ready(function () {
    $(document).off('click', '.closeNotice').on('click', '.closeNotice', function (event) {
        $(this).parent().remove();
        notice_count();
        event.stopPropagation();
    });
    $(document).off('click', '.clear-notice').on('click', '.clear-notice', function (event) {
        $(".notice-list").find('.alert').remove();
        notice_count();
        event.stopPropagation();
    });

    $(document).off('input', '.searchLeftContent').on('input', '.searchLeftContent', function () {
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
