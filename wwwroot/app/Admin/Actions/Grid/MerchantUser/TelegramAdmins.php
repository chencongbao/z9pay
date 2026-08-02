<?php

namespace App\Admin\Actions\Grid\MerchantUser;

use Dcat\Admin\Admin;
use Dcat\Admin\Grid\RowAction;
use Dcat\Admin\Widgets\Modal;
use App\Admin\Controllers\MerchantTelegramAdminController;

class TelegramAdmins extends RowAction
{
    protected $title = '<i class="fa fa-paper-plane"></i> 商户群管理员';

    public function render()
    {
        return Modal::make()->xl()->title($this->title)->body(MerchantTelegramAdminController::make()->payload(['mid' => $this->getKey()]))->button($this->title);
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('merchant-user-edit');
    }
}
