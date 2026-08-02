<?php

namespace App\Admin\Actions\Grid\TransferOrder;

use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\RowAction;
use App\Admin\Controllers\TransferOrderLogController;

class Logs extends RowAction
{
    protected $title = '订单日志';

    public function render()
    {
        return Modal::make()->xl()->title($this->title)->body(TransferOrderLogController::make()->payload(['transfer_order_id' => $this->getKey()]))->button($this->title);
    }
}
