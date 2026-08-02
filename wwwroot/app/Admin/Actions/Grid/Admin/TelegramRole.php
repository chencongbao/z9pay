<?php

namespace App\Admin\Actions\Grid\Admin;

use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\RowAction;
use App\Admin\Forms\Admin\TelegramRole as TelegramRoleForm;

class TelegramRole extends RowAction
{
    protected $title = '<i class="fa fa-paper-plane"></i> 飞机权限';

    public function render()
    {
        $form = TelegramRoleForm::make()->payload(['id' => $this->getKey()]);

        return Modal::make()->lg()->title($this->title)->body($form)->button($this->title);
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('admin-user-telegram-role');
    }
}
