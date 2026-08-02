<?php

namespace App\Admin\Actions\Grid\UserBank;

use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\BatchAction;
use App\Admin\Forms\UserBank\BatchUpdateLimitMinMaxAmountForm;

class BatchUpdateLimitMinMaxAmount extends BatchAction
{
    protected $title = '<button class="btn btn-primary">批量修改单笔限额</button>';

    public function render()
    {
        $form = BatchUpdateLimitMinMaxAmountForm::make()->payload(['id' => $this->getKey()]);

        return Modal::make()->lg()->title('批量修改单笔限额')->body($form)->onLoad($this->getModalScript())->button($this->title);
    }

    protected function getModalScript()
    {
        return <<<JS
var key = {$this->getSelectedKeysScript()}

$('#user-bank-id').val(key);
JS;
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('user-bank-batch-limit');
    }
}
