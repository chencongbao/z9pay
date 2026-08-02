<?php

namespace App\Admin\Actions\Grid\TransferOrder;

use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\BatchAction;
use App\Admin\Forms\TransferOrder\BatchTransferActionForm;

class BatchTransferAction extends BatchAction
{
    protected $title = '<button class="btn btn-primary">批量代付操作</button>';

    public function render()
    {
        $form = BatchTransferActionForm::make()->payload(['id' => $this->getKey()]);

        return Modal::make()->lg()->title('批量代付操作')->body($form)->onLoad($this->getModalScript())->button($this->title);
    }

    protected function getModalScript()
    {
        // 同步批量选中ID到弹窗隐藏字段。
        return <<<JS
var key = {$this->getSelectedKeysScript()}

$('#transfer-id').val(key);
JS;
    }
}
