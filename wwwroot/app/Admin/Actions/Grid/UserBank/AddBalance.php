<?php

namespace App\Admin\Actions\Grid\UserBank;

use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\RowAction;
use App\Admin\Forms\UserBank\AddBalance as AddBalanceForm;

class AddBalance extends RowAction
{
    protected $title = '<i class="feather icon-plus"></i> 收款金额加项';

    public function render()
    {
        $form = AddBalanceForm::make()->payload(['id' => $this->getKey()]);

        return Modal::make()->lg()->title($this->title)->body($form)->button($this->title);
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('user-bank-balance-add');
    }
}
