<?php

namespace App\Admin\Actions\Grid\SettlementOrder;

use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\RowAction;
use App\Admin\Forms\SettlementOrder\OrderFail as OrderFailForm;

class OrderFail extends RowAction
{
    protected $title = '失败';

    public function render()
    {
        $form = OrderFailForm::make()->payload(['id' => $this->getKey()]);

        return Modal::make()->lg()->title($this->title)->body($form)->button($this->title);
    }
}
