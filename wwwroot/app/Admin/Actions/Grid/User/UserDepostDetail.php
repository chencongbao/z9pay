<?php

namespace App\Admin\Actions\Grid\User;

use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\RowAction;
use App\Admin\Controllers\UserDepositDetailController;

class UserDepostDetail extends RowAction
{
    protected $title = '<i class="feather icon-eye"></i>  保证金明细';

    public function render()
    {
        return Modal::make()->xl()->title($this->title)->body(UserDepositDetailController::make()->payload(['user_id' => $this->getKey()]))->button($this->title);
    }
}
