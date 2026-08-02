<li class="dropdown dropdown-user nav-item">
    <a class="dropdown-toggle nav-link dropdown-user-link" href="#" data-toggle="dropdown">
        <div class="user-nav d-sm-flex d-none">
            <span class="user-name text-bold-600">{{merchant_info_name()}}</span>
            <span class="user-status"><i class="fa fa-circle text-success"></i> {{__('admin.online')}}</span>
        </div>
        <span>
            <img class="round" src="{{asset('vendor/dcat-admin/images/default-avatar.jpg')}}" alt="avatar" height="40" width="40">
        </span>
    </a>
    <div class="dropdown-menu dropdown-menu-right" style="left: inherit; right: 0px;">
        <a href="javascript:;" class="dropdown-item" data-toggle="modal" data-target="#modal-update-password"><i class="feather icon-user"></i> {{__('admin.update_login_password')}}</a>
        <div class="dropdown-divider"></div>
        <a href="javascript:;" class="dropdown-item" data-toggle="modal" data-target="#modal-amount-password"><i class="fa fa-money"></i> {{__('admin.update_money_password')}}</a>
        <div class="dropdown-divider"></div>
        <a class="dropdown-item" href="/{{config('merchant-admin.route.prefix')}}/auth/logout">
            <i class="feather icon-power"></i> {{__('admin.logout')}}
        </a>
    </div>
</li>

