<?php

namespace App\Admin\Actions\Grid\SettlementOrder;

use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\RowAction;
use App\Admin\Forms\SettlementOrder\Channel as ChannelForm;

class Channel extends RowAction
{
    protected $title = '代付到渠道';

    public function render()
    {
        $form = ChannelForm::make()->payload(['id' => $this->getKey()]);

        return Modal::make()->lg()->title($this->title)->body($form)->button($this->title);
    }
}
