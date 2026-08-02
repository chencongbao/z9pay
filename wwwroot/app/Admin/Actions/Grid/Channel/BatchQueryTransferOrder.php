<?php

namespace App\Admin\Actions\Grid\Channel;

use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\RowAction;
use App\Admin\Forms\Channel\BatchQueryTransferOrderForm;

class BatchQueryTransferOrder extends RowAction
{
    protected $title = '<i class="feather icon-search"></i> 代付批量查询';

    public function render()
    {
        $form = BatchQueryTransferOrderForm::make()->payload(['id' => $this->getKey()]);

        return Modal::make()->lg()->title($this->title)->body($form)->button($this->title);
    }
}
