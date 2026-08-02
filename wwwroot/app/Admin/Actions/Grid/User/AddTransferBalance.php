<?php

namespace App\Admin\Actions\Grid\User;

use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\RowAction;
use App\Admin\Forms\User\AddTransferBalance as AddTransferBalanceForm;

class AddTransferBalance extends RowAction
{
    protected $title = '<i class="feather icon-plus"></i>  代付账户加项';

    public function render()
    {
        $form = AddTransferBalanceForm::make()->payload(['id' => $this->getKey()]);

        return Modal::make()->lg()->title($this->title)->body($form)->button($this->title);
    }
}
