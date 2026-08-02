<?php

namespace App\Admin\Actions\Grid\TransferOrder;

use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\BatchAction;
use App\Admin\Forms\TransferOrder\BatchCallbackForm;

class BatchCallback extends BatchAction
{
    protected $title = '<button class="btn btn-primary">批量回调</button>';

    public function render()
    {
        $form = BatchCallbackForm::make()->payload(['id' => $this->getKey()]);

        return Modal::make()->lg()->title('批量回调')->body($form)->button($this->title);
    }
}
