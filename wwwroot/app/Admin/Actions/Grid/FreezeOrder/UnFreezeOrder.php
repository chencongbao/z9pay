<?php

namespace App\Admin\Actions\Grid\FreezeOrder;

use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\RowAction;
use App\Admin\Forms\FreezeOrder\UnFreezeOrder as UnFreezeOrderForm;

class UnFreezeOrder extends RowAction
{
    protected $title = '解冻';

    public function render()
    {
        $form = UnFreezeOrderForm::make()->payload(['id' => $this->getKey()]);

        return Modal::make()->lg()->title($this->title)->body($form)->button($this->title);
    }
}
