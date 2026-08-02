<?php

namespace App\Admin\Actions\Grid\User;

use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\RowAction;
use App\Admin\Forms\User\ReduceYajinBalance as ReduceYajinBalanceForm;

class ReduceYajinBalance extends RowAction
{
    protected $title = '<i class="feather icon-minus"></i> 保证金减项';

    public function render()
    {
        $form = ReduceYajinBalanceForm::make()->payload(['id' => $this->getKey()]);

        return Modal::make()->lg()->title($this->title)->body($form)->button($this->title);
    }
}
