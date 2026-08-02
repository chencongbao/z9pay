<?php

namespace App\Admin\Actions\Grid\User;

use Dcat\Admin\Grid\RowAction;
use Dcat\Admin\Widgets\Modal;
use App\Admin\Controllers\UserDayBalanceLogController;

class UserDayBalanceLog extends RowAction
{
    protected $title = '<i class="feather icon-dollar-sign"></i> 日切余额日志';

    public function render()
    {
        return Modal::make()->xl()->title($this->title)->body(UserDayBalanceLogController::make()->payload(['uid' => $this->getKey()]))->button($this->title);
    }
}
