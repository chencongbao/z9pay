<?php

namespace App\Admin\Actions\Grid\TransferOrder;

use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\RowAction;
use App\Admin\Forms\TransferOrder\OrderFail as OrderFailForm;

class OrderFail extends RowAction
{
    protected $title = '手动失败';

    public function render()
    {
        $form = OrderFailForm::make()->payload(['id' => $this->getKey()]);

        return Modal::make()->lg()->title($this->title)->body($form)->button($this->title);
    }
}
