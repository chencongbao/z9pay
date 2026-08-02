<?php

use Dcat\Admin\Admin;
use Dcat\Admin\Grid;
use Dcat\Admin\Form;
use Dcat\Admin\Layout\Navbar;
use Dcat\Admin\Grid\Column;

Admin::js('/vendor/plugins/jquery.cookie.min.js');
Admin::css('/vendor/plugins/layui/css/layui.css');
Admin::js('/vendor/plugins/layui/layui.js');
Admin::css('/style/style.css');
Admin::js('/vendor/vh-sticky-table-header/sticky-table-header.js');
Admin::js('/style/admin-grid-sticky.js');
Admin::js('/style/admin-modal-select2.js');
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
});


if(Admin::user()){
    admin_inject_section(Admin::SECTION['BODY_INNER_AFTER'], function (){
        return view('merchant-admin.system-notice')->render();
    });
    Admin::navbar(function (Navbar $navbar) {
        $navbar->right(view('language'));
        $navbar->right(App\MerchantAdmin\Actions\Dashboard\AmountPassword::make()->render());
        $navbar->right(App\MerchantAdmin\Actions\Dashboard\UpdatePassword::make()->render());
    });
    admin_inject_section(Admin::SECTION['APP_INNER_BEFORE'], view('filter'));
    admin_inject_section(Admin::SECTION['NAVBAR_USER_PANEL'], view('merchant-admin.navbar-user-panel'));
    Column::extend('status', \App\Admin\Extensions\Displayers\Status::class);
    Column::extend('google', \App\Admin\Extensions\Displayers\Google::class);
    Column::extend('text', \App\Admin\Extensions\Displayers\Text::class);
    Column::extend('amount', \App\Admin\Extensions\Displayers\Amount::class);
    Grid\Column::macro('center', function () {
        return $this->setAttributes(['class' => 'text-center'])->setHeaderAttributes(['class' => 'text-center']);
    });
    Grid\Column::macro('top', function () {
        return $this->setAttributes(['style' => 'vertical-align: top']);
    });
}
