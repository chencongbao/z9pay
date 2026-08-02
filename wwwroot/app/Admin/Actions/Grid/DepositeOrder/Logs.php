<?php

namespace App\Admin\Actions\Grid\DepositeOrder;

use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\RowAction;
use App\Admin\Controllers\DepositeOrderLogController;

class Logs extends RowAction
{
    protected $title = '订单日志';

    public function render()
    {
        return Modal::make()->xl()->title($this->title)->body(DepositeOrderLogController::make()->payload(['deposit_order_id' => $this->getKey()]))->button($this->title);
    }
}
