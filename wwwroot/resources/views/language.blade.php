<ul class="nav navbar-nav">
    <li class="dropdown dropdown-user nav-item">
        <a class="dropdown-toggle nav-link" href="#" id="dropdown-flag" data-toggle="dropdown" style="display: flex;align-items: center">
            <i class="feather icon-globe" style="font-size: 20px;padding-right: 5px;"></i>
            <span class="selected-language">{{__('admin.'.config('app.locale'))}}</span>
        </a>
        <div class="dropdown-menu dropdown-menu-right" style="left: inherit; right: 0px;">
            <span style="cursor: pointer"><a href="javascript:;" onclick="setLocale('zh_CN')" class="dropdown-item"><img src="{{asset('img/chinese.jpg')}}?t=1" height="20"/> {{__('admin.zh_CN')}}</a></span>
            <div class="dropdown-divider"></div>
            <span style="cursor: pointer"><a href="javascript:;" onclick="setLocale('en')" class="dropdown-item"><img src="{{asset('img/english.jpg')}}?t=1" height="20"/> {{__('admin.en')}}</a></span>
            <div class="dropdown-divider"></div>
            <span style="cursor: pointer"><a href="javascript:;" onclick="setLocale('vi')" class="dropdown-item"><img src="{{asset('img/vi.png')}}?t=1" height="20"/> {{__('admin.vi')}}</a></span>
        </div>
    </li>
</ul>

<script>
    function setLocale(locale) {
        $.cookie('locale', `${locale}`, { expires: 365, path: '/' });
        window.location.reload();
    }
</script>

