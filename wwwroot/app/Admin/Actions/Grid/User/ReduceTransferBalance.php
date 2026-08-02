<?php

namespace App\Admin\Actions\Grid\User;

use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\RowAction;
use App\Admin\Forms\User\ReduceTransferBalance as ReduceTransferBalanceForm;

class ReduceTransferBalance extends RowAction
{
    protected $title = '<i class="feather icon-minus"></i> 代付账户减项';

    public function render()
    {
        $form = ReduceTransferBalanceForm::make()->payload(['id' => $this->getKey()]);

        return Modal::make()->lg()->title($this->title)->body($form)->button($this->title);
    }
}
