<?php

namespace App\AgentAdmin\Actions\Dashboard;

use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Actions\Action;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use App\AgentAdmin\Form\UpdatePassword as UpdatePasswordForm;

class UpdatePassword extends Action
{
    private const MODAL_ID = 'modal-update-password';

    public function render()
    {
        $this->setLocaleFromCookie();

        return Modal::make()
            ->lg()
            ->id(self::MODAL_ID)
            ->title(__('admin.update_login_password'))
            ->body(UpdatePasswordForm::make())
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
        return '<a href="javascript:;" class="dropdown-item hidden"><i class="feather icon-user"></i> ' . __('admin.update_login_password') . '</a>';
    }
}
