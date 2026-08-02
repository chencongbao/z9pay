<?php

namespace App\Admin\Actions\Grid\TransferOrder;

use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\RowAction;
use App\Admin\Forms\TransferOrder\Channel as TransferOrderChannelForm;

class Channel extends RowAction
{
    protected $title = '代付到渠道';

    public function render()
    {
        $form = TransferOrderChannelForm::make()->payload(['id' => $this->getKey()]);

        return Modal::make()->lg()->title($this->title)->body($form)->button($this->title);
    }
}
