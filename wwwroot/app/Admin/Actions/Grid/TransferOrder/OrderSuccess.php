<?php

namespace App\Admin\Actions\Grid\TransferOrder;

use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\RowAction;
use App\Admin\Forms\TransferOrder\OrderSuccess as OrderSuccessForm;

class OrderSuccess extends RowAction
{
    protected $title = '手动成功';

    public function render()
    {
        $form = OrderSuccessForm::make()->payload(['id' => $this->getKey()]);

        return Modal::make()->lg()->title($this->title)->body($form)->button($this->title);
    }
}
