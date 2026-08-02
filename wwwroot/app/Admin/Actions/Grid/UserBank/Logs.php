<?php

namespace App\Admin\Actions\Grid\UserBank;

use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\RowAction;
use App\Admin\Controllers\UserBankActionLogController;

class Logs extends RowAction
{
    protected $title = '<i class="feather icon-disc"></i> 操作日志';

    public function render()
    {
        return Modal::make()->xl()->title($this->title)->body(UserBankActionLogController::make()->payload(['user_bank_id' => $this->getKey()]))->button($this->title);
    }
}
