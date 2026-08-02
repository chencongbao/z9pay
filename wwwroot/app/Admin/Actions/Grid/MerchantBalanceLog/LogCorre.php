<?php

namespace App\Admin\Actions\Grid\MerchantBalanceLog;

use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\RowAction;
use App\Admin\Forms\MerchantBalanceLog\LogCorre as LogCorreForm;

class LogCorre extends RowAction
{
    protected $title = '流水冲正';

    public function confirm()
    {
        return ['确认操作?', '确认对当前商户流水执行冲正操作吗？'];
    }

    public function render()
    {
        $form = LogCorreForm::make()->payload(['id' => $this->getKey()]);

        return Modal::make()->lg()->title($this->title)->body($form)->button($this->title);
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('merchant-balance-log-corre');
    }
}
