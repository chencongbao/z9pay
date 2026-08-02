<?php

namespace App\Admin\Actions\Grid\UserBank;

use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\RowAction;
use App\Admin\Forms\UserBank\RecoveForm;

class Recover extends RowAction
{
    protected $title = '<i class="feather icon-anchor"></i> 还原数据';

    public function render()
    {
        $form = RecoveForm::make()->payload(['id' => $this->getKey()]);

        return Modal::make()->lg()->title($this->title)->body($form)->button($this->title);
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('user-bank-restore');
    }
}
