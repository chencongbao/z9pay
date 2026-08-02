<?php

namespace App\Admin\Actions\Grid\Channel;

use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\RowAction;
use App\Admin\Forms\Channel\ChannelTransferCheckInfoForm;

class ChannelTransferCheckInfo extends RowAction
{
    protected $title = '<i class="feather icon-eye"></i> 代付反查信息';

    public function render()
    {
        $form = ChannelTransferCheckInfoForm::make()->payload(['id' => $this->getKey()]);

        return Modal::make()->lg()->title($this->title)->body($form)->button($this->title);
    }
}
