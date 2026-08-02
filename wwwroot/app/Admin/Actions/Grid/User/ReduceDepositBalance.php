<?php

namespace App\Admin\Actions\Grid\User;

use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\RowAction;
use App\Admin\Forms\User\ReduceDepositBalance as ReduceDepositBalanceForm;

class ReduceDepositBalance extends RowAction
{
    protected $title = '<i class="feather icon-minus"></i> 代收账户减项';

    public function render()
    {
        $form = ReduceDepositBalanceForm::make()->payload(['id' => $this->getKey()]);

        return Modal::make()->lg()->title($this->title)->body($form)->button($this->title);
    }
}
