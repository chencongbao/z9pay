<?php

namespace App\Admin\Actions\Grid\DepositeOrder;

use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\RowAction;
use App\Admin\Forms\DepositeOrder\FreezeOrder as FreezeOrderForm;

class FreezeOrder extends RowAction
{
    protected $title = '冻结订单';

    public function render()
    {
        $form = FreezeOrderForm::make()->payload(['id' => $this->getKey()]);

        return Modal::make()->lg()->title($this->title)->body($form)->button($this->title);
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('deposit-order-freeze');
    }
}
