<?php

namespace App\Admin\Actions\Grid\User;

use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\RowAction;
use App\Admin\Forms\User\AddCommissionBalance as AddCommissionBalanceForm;

class AddCommissionBalance extends RowAction
{
    protected $title = '<i class="feather icon-plus"></i>  佣金账户加项';

    public function render()
    {
        $form = AddCommissionBalanceForm::make()->payload(['id' => $this->getKey()]);

        return Modal::make()->lg()->title($this->title)->body($form)->button($this->title);
    }
}
