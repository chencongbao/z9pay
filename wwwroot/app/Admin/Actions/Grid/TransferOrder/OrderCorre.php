<?php

namespace App\Admin\Actions\Grid\TransferOrder;

use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\RowAction;
use App\Admin\Forms\TransferOrder\OrderCorre as OrderCorreForm;

class OrderCorre extends RowAction
{
    protected $title = '订单冲正';

    public function render()
    {
        $form = OrderCorreForm::make()->payload(['id' => $this->getKey()]);

        return Modal::make()->lg()->title($this->title)->body($form)->button($this->title);
    }
}
