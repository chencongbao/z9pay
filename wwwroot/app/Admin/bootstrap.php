<?php

use Dcat\Admin\Admin;
use Dcat\Admin\Grid;
use Dcat\Admin\Grid\Filter;
use Dcat\Admin\Form;
use Dcat\Admin\Layout\Navbar;
use Dcat\Admin\Grid\Column;
use Illuminate\Support\Facades\Cookie;


Admin::js('/vendor/plugins/jquery.cookie.min.js');
Admin::css('/vendor/plugins/layui/css/layui.css');
Admin::js('/vendor/plugins/layui/layui.js');
Admin::js('/voice/js/audioPlayPlugin.js');
Admin::js('/style/notice.js');
Admin::css(admin_asset_versioned_path('/style/style.css'));
Admin::css('/vendor/plugins/boostrap-treeview/bootstrap-treeview.min.css');
Admin::js('/vendor/plugins/boostrap-treeview/bootstrap-treeview.js');
Admin::js(admin_asset_versioned_path('/vendor/vh-sticky-table-header/sticky-table-header.js'));
if (config('admin.layout.horizontal_menu')) {
    Admin::css(admin_asset_versioned_path('/style/admin-horizontal-menu.css'));
    Admin::js(admin_asset_versioned_path('/style/admin-horizontal-menu.js'));
}
Admin::js(admin_asset_versioned_path('/style/admin-grid-sticky.js'));
Admin::js(admin_asset_versioned_path('/style/admin-modal-select2.js'));
Admin::js(admin_asset_versioned_path('/style/admin-http-error-trace.js'));

Admin::js(admin_asset_versioned_path('/style/admin-iframe-tab-link.js'));

if (! config('iframe_tab.enable')) {
    Admin::style(<<<CSS
.content-wrapper > .content-header {
    padding-top: 40px;
}
CSS);
}

if(Admin::user()){
    admin_inject_section(Admin::SECTION['BODY_INNER_AFTER'], function (){
        $html = view('admin.system-notice', ['notice_voice_on' => Cookie::has('notice_voice_on')])->render();

        if (config('iframe_tab.enable') && config('default.admin_page_refresh_button_on', true) && ! request()->boolean('iframe_tab_child')) {
            $html .= <<<HTML
<button type="button" class="admin-page-refresh-btn" title="刷新页面" aria-label="刷新页面">
    <i class="feather icon-refresh-cw"></i>
</button>
HTML;
        }

        return $html;
    });
    admin_inject_section(Admin::SECTION['APP_INNER_BEFORE'], view('filter'));

    Admin::navbar(function (Navbar $navbar) {
        $navbar->right(view('language'));
        $navbar->right(view('admin.header-notice'));
    });

    Form::resolving(function (Form $form) {
        $form->disableEditingCheck();
        $form->disableCreatingCheck();
        $form->disableViewCheck();
        $form->tools(function (Form\Tools $tools) {
            $tools->disableDelete();
            $tools->disableView();
        });
    });

    Grid::resolving(function (Grid $grid) {
        $grid->model()->orderBy($grid->getKeyName(),'desc');
        $grid->disableBatchDelete();
        $grid->disableViewButton();
        $grid->disableRowSelector();
        $grid->withBorder();
        $grid->paginate(20);
        $grid->perPages([10, 20, 30, 40, 50,100,500]);
    });

    Column::extend('status', \App\Admin\Extensions\Displayers\Status::class);
    Column::extend('google', \App\Admin\Extensions\Displayers\Google::class);
    Column::extend('amount', \App\Admin\Extensions\Displayers\Amount::class);
    Column::extend('text', \App\Admin\Extensions\Displayers\Text::class);
    Form::extend('passwordTool', \App\Admin\Extensions\Form\PasswordTool::class);
    Filter::extend('monthRangeDatetime', \App\Admin\Extensions\Filter\MonthRangeDatetime::class);
    Grid\Column::macro('center', function () {
        return $this->setAttributes(['class' => 'text-center'])->setHeaderAttributes(['class' => 'text-center']);
    });
    Grid\Column::macro('top', function () {
        return $this->setAttributes(['style' => 'vertical-align: top']);
    });

    app('view')->prependNamespace('admin', resource_path('views/dcat'));
}
