<?php

namespace App\Admin\Actions\Grid\DepositeOrder;

use Dcat\Admin\Grid\RowAction;
use Dcat\Admin\Widgets\Modal;
use App\Admin\Renderable\MerchantCallback\Records;

class CallbackRecords extends RowAction
{
    protected $title = '回调记录';

    public function render()
    {
        return Modal::make()
            ->xl()
            ->title($this->title)
            ->body(Records::make()->payload(['type' => 1, 'order_id' => $this->getKey()]))
            ->button($this->title);
    }
}
