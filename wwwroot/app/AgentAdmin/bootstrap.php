<?php

use Dcat\Admin\Admin;
use Dcat\Admin\Grid\Column;
use Dcat\Admin\Layout\Navbar;

Admin::js('/vendor/plugins/jquery.cookie.min.js');
Admin::css('/vendor/plugins/layui/css/layui.css');
Admin::js('/vendor/plugins/layui/layui.js');
Admin::css('/style/style.css');
Admin::js('/style/notice.js');
Admin::css('/vendor/plugins/boostrap-treeview/bootstrap-treeview.min.css');
Admin::js('/vendor/plugins/boostrap-treeview/bootstrap-treeview.js');
Admin::js('/vendor/vh-sticky-table-header/sticky-table-header.js');
Admin::js('/style/admin-grid-sticky.js');
Admin::js('/style/admin-modal-select2.js');
if(Admin::user()){
    admin_inject_section(Admin::SECTION['APP_INNER_BEFORE'], view('filter'));
    admin_inject_section(Admin::SECTION['BODY_INNER_AFTER'], function () {
        return view('agent-admin.system-notice')->render() . view('agent-admin.security-history')->render();
    });
    admin_inject_section(Admin::SECTION['NAVBAR_USER_PANEL'], view('agent-admin.navbar-user-panel'));
    app('view')->prependNamespace('admin', resource_path('views/dcat'));

    Admin::navbar(function (Navbar $navbar) {
        $navbar->right(view('language'));
        $navbar->right(App\AgentAdmin\Actions\Dashboard\UpdatePassword::make()->render());
    });
}

Column::extend('status', \App\Admin\Extensions\Displayers\Status::class);
Column::extend('amount', \App\Admin\Extensions\Displayers\Amount::class);
Column::macro('center', function () {
    return $this->setAttributes(['class' => 'text-center'])->setHeaderAttributes(['class' => 'text-center']);
});
Column::macro('top', function () {
    return $this->setAttributes(['style' => 'vertical-align: top']);
});

app('view')->prependNamespace('admin', resource_path('views/dcat'));
