<?php

namespace App\Admin\Actions\Grid\UserAgent;

use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\RowAction;
use App\Admin\Forms\UserAgent\ResetGooglePassword as ResetGooglePasswordForm;

class ResetGooglePassword extends RowAction
{
    protected $title = '<i class="fa fa-google"></i> 谷歌验证码';

    public function render()
    {
        $form = ResetGooglePasswordForm::make()->payload(['id' => $this->getKey()]);

        return Modal::make()->lg()->title($this->title)->body($form)->button($this->title);
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('user-agent-reset-googlecode');
    }
}
