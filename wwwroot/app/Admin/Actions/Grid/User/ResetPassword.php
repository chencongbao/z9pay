<?php

namespace App\Admin\Actions\Grid\User;

use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\RowAction;
use App\Admin\Forms\User\ResetPassword as ResetPasswordForm;

class ResetPassword extends RowAction
{
    protected $title = '<i class="feather icon-lock"></i> 重置密码';

    public function render()
    {
        $form = ResetPasswordForm::make()->payload(['id' => $this->getKey()]);

        return Modal::make()->lg()->title($this->title)->body($form)->button($this->title);
    }
}
