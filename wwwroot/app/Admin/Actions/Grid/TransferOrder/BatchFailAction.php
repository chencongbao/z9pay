<?php

namespace App\Admin\Actions\Grid\TransferOrder;

use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\BatchAction;
use App\Admin\Forms\TransferOrder\BatchFailActionForm;

class BatchFailAction extends BatchAction
{
    protected $title = '<button class="btn btn-primary">批量驳回操作</button>';

    public function render()
    {
        $form = BatchFailActionForm::make()->payload(['id' => $this->getKey()]);

        return Modal::make()->lg()->title('批量驳回操作')->body($form)->onLoad($this->getModalScript())->button($this->title);
    }

    protected function getModalScript()
    {
        return <<<JS
var key = {$this->getSelectedKeysScript()}

$('#transfer-id').val(key);
JS;
    }
}
