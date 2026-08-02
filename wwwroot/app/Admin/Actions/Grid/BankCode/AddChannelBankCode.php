<?php

namespace App\Admin\Actions\Grid\BankCode;

use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\RowAction;
use App\Admin\Forms\BankCode\AddChannelBankCode as AddChannelBankCodeForm;

class AddChannelBankCode extends RowAction
{
    protected $title = '新增渠道代码';

    public function render()
    {
        $form = AddChannelBankCodeForm::make()->payload(['id' => $this->getKey()]);

        return Modal::make()->lg()->title($this->title)->body($form)->button($this->title);
    }
}
