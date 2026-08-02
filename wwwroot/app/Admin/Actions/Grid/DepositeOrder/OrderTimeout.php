<?php

namespace App\Admin\Actions\Grid\DepositeOrder;

use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\RowAction;
use App\Admin\Forms\DepositeOrder\OrderTimeout as OrderTimeoutForm;

class OrderTimeout extends RowAction
{
    protected $title = '手动超时';

    public function render()
    {
        $form = OrderTimeoutForm::make()->payload(['id' => $this->getKey()]);

        return Modal::make()->lg()->title($this->title)->body($form)->button($this->title);
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('deposit-order-manual-timeout');
    }
}
