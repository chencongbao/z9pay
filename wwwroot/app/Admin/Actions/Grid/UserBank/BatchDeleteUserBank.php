<?php

namespace App\Admin\Actions\Grid\UserBank;

use Dcat\Admin\Admin;
use Dcat\Admin\Grid\BatchAction;
use Dcat\Admin\Widgets\Modal;
use App\Admin\Forms\UserBank\BatchDeleteForm;

class BatchDeleteUserBank extends BatchAction
{
    protected $title = '<button class="btn btn-danger">批量删除</button>';

    public function render()
    {
        $form = BatchDeleteForm::make()->payload(['id' => $this->getKey()]);

        return Modal::make()->lg()->title('批量删除收款卡')->body($form)->onLoad($this->getModalScript())->button($this->title);
    }

    public function actionScript()
    {
        $warning = __('请选择操作项!');

        return <<<JS
function (data, target, action) {
    var key = {$this->getSelectedKeysScript()}

    if (key.length === 0) {
        Dcat.error('{$warning}');
        return false;
    }
}
JS;
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('user-bank-delete');
    }

    protected function getModalScript(): string
    {
        return <<<JS
var key = {$this->getSelectedKeysScript()}

$('#user-bank-batch-delete-ids').val(key);
JS;
    }
}
