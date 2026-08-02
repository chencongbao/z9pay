<?php

namespace App\Admin\Actions\Grid\MerchantUser;

use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\RowAction;
use App\Admin\Controllers\MerchantDayBalanceLogController;

class MerchantDayBalanceLog extends RowAction
{
    protected $title = '<i class="feather icon-dollar-sign"></i> 日切余额日志';

    public function render()
    {
        return Modal::make()->xl()->title($this->title)->body(MerchantDayBalanceLogController::make()->payload(['mid' => $this->getKey()]))->button($this->title);
    }
}
