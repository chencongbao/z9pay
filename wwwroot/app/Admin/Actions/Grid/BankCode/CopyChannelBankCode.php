<?php

namespace App\Admin\Actions\Grid\BankCode;

use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\BatchAction;
use App\Admin\Forms\BankCode\CopyChannelBankCodeForm;

class CopyChannelBankCode extends BatchAction
{
    protected $title = '<button class="btn btn-primary">渠道编码复制</button>';

    public function render()
    {
        $form = CopyChannelBankCodeForm::make()->payload(['id' => $this->getKey()]);

        return Modal::make()->lg()->title('渠道编码复制')->body($form)->button($this->title);
    }
}
