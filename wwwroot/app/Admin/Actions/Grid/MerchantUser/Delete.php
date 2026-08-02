<?php

namespace App\Admin\Actions\Grid\MerchantUser;

use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\RowAction;
use App\Admin\Forms\MerchantUser\Delete as DeleteForm;

class Delete extends RowAction
{
    protected $title = '<i class="feather icon-trash-2"></i> 删除商户';

    public function render()
    {
        $form = DeleteForm::make()->payload(['id' => $this->getKey()]);

        return Modal::make()->lg()->title($this->title)->body($form)->button($this->title);
    }
}
