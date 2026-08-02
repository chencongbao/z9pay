<?php

namespace App\Admin\Actions\Grid\MerchantBalanceLog;

use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\RowAction;
use App\Admin\Forms\MerchantBalanceLog\ReduceBalance as ReduceBalanceForm;

class ReduceBalance extends RowAction
{
    protected $title = '商户资金减项';

    private int $mid = 0;

    public function __construct($mid = 0)
    {
        $this->mid = (int) $mid;
    }

    public function render()
    {
        $form = ReduceBalanceForm::make()->payload(['id' => $this->getKey(), 'mid' => $this->mid]);

        return Modal::make()->lg()->title($this->title)->body($form)->button('<button class="btn btn-primary">' . $this->title . '</button>');
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('merchant-balance-log-reduce');
    }
}
