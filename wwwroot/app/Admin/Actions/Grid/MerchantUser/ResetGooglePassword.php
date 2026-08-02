<?php

namespace App\Admin\Actions\Grid\MerchantUser;

use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\RowAction;
use App\Admin\Forms\MerchantUser\ResetGooglePassword as ResetGooglePasswordForm;

class ResetGooglePassword extends RowAction
{
    protected $title = '<i class="fa fa-google"></i> 重置谷歌验证';

    public function render()
    {
        $form = ResetGooglePasswordForm::make()->payload(['id' => $this->getKey()]);

        return Modal::make()->lg()->title($this->title)->body($form)->button($this->title);
    }
}
