<?php

namespace App\Admin\Actions\Grid\UserBank;

use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\RowAction;
use App\Admin\Forms\UserBank\ReduceBalance as ReduceBalanceForm;

class ReduceBalance extends RowAction
{
    protected $title = '<i class="feather icon-minus"></i> 收款金额减项';

    public function render()
    {
        $form = ReduceBalanceForm::make()->payload(['id' => $this->getKey()]);

        return Modal::make()->lg()->title($this->title)->body($form)->button($this->title);
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('user-bank-balance-reduce');
    }
}
