<?php

namespace App\Admin\Actions\Grid\User;

use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\RowAction;
use App\Admin\Forms\User\AddDepositBalance as AddDepositBalanceForm;

class AddDepositBalance extends RowAction
{
    protected $title = '<i class="feather icon-plus"></i>  代收账户加项';

    public function render()
    {
        $form = AddDepositBalanceForm::make()->payload(['id' => $this->getKey()]);

        return Modal::make()->lg()->title($this->title)->body($form)->button($this->title);
    }
}
