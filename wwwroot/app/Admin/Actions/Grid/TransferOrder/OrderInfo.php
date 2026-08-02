<?php

namespace App\Admin\Actions\Grid\TransferOrder;

use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\RowAction;
use App\Admin\Forms\TransferOrder\OrderInfo as OrderInfoForm;

class OrderInfo extends RowAction
{
    protected $title = '订单凭证';

    public function render()
    {
        $form = OrderInfoForm::make()->payload(['id' => $this->getKey()]);

        return Modal::make()->lg()->title($this->title)->body($form)->button($this->title);
    }
}
