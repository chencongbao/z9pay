<?php

namespace App\Admin\Actions\Grid\User;

use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\RowAction;
use App\Admin\Forms\User\AddYajinBalance as AddYajinBalanceForm;

class AddYajinBalance extends RowAction
{
    protected $title = '<i class="feather icon-plus"></i>  保证金加项';

    public function render()
    {
        $form = AddYajinBalanceForm::make()->payload(['id' => $this->getKey()]);

        return Modal::make()->lg()->title($this->title)->body($form)->button($this->title);
    }
}
