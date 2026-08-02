<?php

namespace App\MerchantAdmin\Actions\Dashboard;

use App\MerchantAdmin\Form\AmountPassword as AmountPasswordForm;
use Dcat\Admin\Actions\Action;
use Dcat\Admin\Widgets\Modal;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;

class AmountPassword extends Action
{
    private const MODAL_ID = 'modal-amount-password';

    public function render()
    {
        $this->setLocaleFromCookie();

        return Modal::make()
            ->lg()
            ->id(self::MODAL_ID)
            ->title(__('admin.update_money_password'))
            ->body(AmountPasswordForm::make())
            ->button($this->buttonHtml())
            ->render();
    }

    private function setLocaleFromCookie(): void
    {
        if (Cookie::has('locale')) {
            App::setLocale((string) Cookie::get('locale'));
        }
    }

    private function buttonHtml(): string
    {
        return '<a href="javascript:;" class="dropdown-item hidden"><i class="fa fa-money"></i> ' . __('admin.update_money_password') . '</a>';
    }
}
