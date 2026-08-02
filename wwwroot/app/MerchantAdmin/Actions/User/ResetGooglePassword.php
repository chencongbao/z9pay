<?php

namespace App\MerchantAdmin\Actions\User;

use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\RowAction;
use App\MerchantAdmin\Form\User\ResetGooglePassword as ResetGooglePasswordForm;

class ResetGooglePassword extends RowAction
{
    public function render()
    {
        return Modal::make()->lg()->title(__('admin.reset_google_2fa_code'))->body(ResetGooglePasswordForm::make()->payload(['id' => $this->getKey()]))->button($this->buttonHtml());
    }

    private function buttonHtml(): string
    {
        return '<i class="fa fa-google"></i> ' . __('admin.reset_google_2fa_code');
    }
}
