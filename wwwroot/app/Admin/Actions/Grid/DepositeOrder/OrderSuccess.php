<?php

namespace App\Admin\Actions\Grid\DepositeOrder;

use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\RowAction;
use App\Admin\Forms\DepositeOrder\OrderSuccess as OrderSuccessForm;

class OrderSuccess extends RowAction
{
    protected $title = '人工补单';

    public function render()
    {
        $form = OrderSuccessForm::make()->payload(['id' => $this->getKey()]);

        return Modal::make()->lg()->title($this->title)->body($form)->button($this->title);
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('deposit-order-manual-success');
    }
}
